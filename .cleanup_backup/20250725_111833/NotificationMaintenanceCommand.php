<?php
// File: app/Console/Commands/NotificationMaintenanceCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use Carbon\Carbon;

class NotificationMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:maintenance
                           {action : The maintenance action to perform (cleanup|retry|stats|reset)}
                           {--days=30 : Number of days to consider for cleanup}
                           {--dry-run : Show what would be done without actually doing it}
                           {--force : Force the action without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Perform maintenance operations on the notification system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🔧 Notification System Maintenance");
        $this->info("Action: {$action}");

        if ($dryRun) {
            $this->warn("⚠️  DRY RUN MODE - No changes will be made");
        }

        switch ($action) {
            case 'cleanup':
                return $this->cleanup();

            case 'retry':
                return $this->retryFailed();

            case 'stats':
                return $this->showStats();

            case 'reset':
                return $this->resetFailed();

            default:
                $this->error("❌ Unknown action: {$action}");
                $this->info("Available actions: cleanup, retry, stats, reset");
                return 1;
        }
    }

    /**
     * Cleanup old notifications
     */
    private function cleanup(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🧹 Cleaning up notifications older than {$days} days...");

        $cutoffDate = Carbon::now()->subDays($days);

        // Count notifications to be deleted
        $query = Notification::where('created_at', '<', $cutoffDate);

        // Separate by status for better reporting
        $sentCount = $query->clone()->where('status', 'sent')->count();
        $failedCount = $query->clone()->where('status', 'failed')->count();
        $cancelledCount = $query->clone()->where('status', 'cancelled')->count();
        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info("✅ No old notifications found to cleanup.");
            return 0;
        }

        $this->table(
            ['Status', 'Count'],
            [
                ['Sent', number_format($sentCount)],
                ['Failed', number_format($failedCount)],
                ['Cancelled', number_format($cancelledCount)],
                ['Total', number_format($totalCount)],
            ]
        );

        if ($dryRun) {
            $this->info("🔍 DRY RUN: Would delete {$totalCount} notifications");
            return 0;
        }

        if (!$force && !$this->confirm("Do you want to delete these {$totalCount} notifications?")) {
            $this->info("❌ Operation cancelled.");
            return 0;
        }

        // Perform cleanup
        $deletedCount = $query->delete();

        $this->info("✅ Cleanup completed: {$deletedCount} notifications deleted");

        Log::info('Notification cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateString(),
            'executed_by' => 'command'
        ]);

        return 0;
    }

    /**
     * Retry failed notifications
     */
    private function retryFailed(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🔄 Finding failed notifications that can be retried...");

        $retryableNotifications = Notification::where('status', 'failed')
            ->where('retry_count', '<', Notification::MAX_RETRY_ATTEMPTS)
            ->where('created_at', '>=', Carbon::now()->subDays(7)) // Only retry recent failures
            ->get();

        if ($retryableNotifications->isEmpty()) {
            $this->info("✅ No retryable failed notifications found.");
            return 0;
        }

        $this->info("Found {$retryableNotifications->count()} notifications that can be retried:");

        $this->table(
            ['ID', 'Recipient', 'Subject', 'Failed At', 'Retry Count', 'Error'],
            $retryableNotifications->map(function ($notification) {
                return [
                    $notification->id,
                    Str::limit($notification->recipient_email, 30),
                    Str::limit($notification->subject, 40),
                    $notification->updated_at->format('Y-m-d H:i'),
                    $notification->retry_count,
                    Str::limit($notification->error_message ?? 'Unknown', 50)
                ];
            })->toArray()
        );

        if ($dryRun) {
            $this->info("🔍 DRY RUN: Would retry {$retryableNotifications->count()} notifications");
            return 0;
        }

        if (!$force && !$this->confirm("Do you want to retry these {$retryableNotifications->count()} notifications?")) {
            $this->info("❌ Operation cancelled.");
            return 0;
        }

        $retriedCount = 0;
        $this->withProgressBar($retryableNotifications, function ($notification) use (&$retriedCount) {
            try {
                // Reset notification status
                $notification->update([
                    'status' => 'pending',
                    'error_message' => null
                ]);

                // Dispatch job to retry
                SendNotificationJob::dispatch($notification);
                $retriedCount++;

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to retry notification {$notification->id}: {$e->getMessage()}");
            }
        });

        $this->newLine(2);
        $this->info("✅ Retry completed: {$retriedCount} notifications queued for retry");

        Log::info('Failed notifications retry completed', [
            'retried_count' => $retriedCount,
            'executed_by' => 'command'
        ]);

        return 0;
    }

    /**
     * Show notification statistics
     */
    private function showStats(): int
    {
        $days = (int) $this->option('days');

        $this->info("📊 Notification Statistics (Last {$days} days)");

        $cutoffDate = Carbon::now()->subDays($days);

        // Overall stats
        $stats = Notification::where('created_at', '>=', $cutoffDate)
            ->selectRaw('
                status,
                recipient_type,
                COUNT(*) as count,
                AVG(retry_count) as avg_retries,
                MAX(created_at) as latest,
                MIN(created_at) as earliest
            ')
            ->groupBy('status', 'recipient_type')
            ->get();

        // Summary table
        $summaryData = Notification::where('created_at', '>=', $cutoffDate)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled,
                AVG(retry_count) as avg_retries
            ')
            ->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Notifications', number_format($summaryData->total)],
                ['Sent', number_format($summaryData->sent) . ' (' . ($summaryData->total > 0 ? round(($summaryData->sent / $summaryData->total) * 100, 1) : 0) . '%)'],
                ['Pending', number_format($summaryData->pending)],
                ['Failed', number_format($summaryData->failed)],
                ['Cancelled', number_format($summaryData->cancelled)],
                ['Average Retries', round($summaryData->avg_retries, 2)],
            ]
        );

        // By recipient type
        $this->newLine();
        $this->info("📈 By Recipient Type:");

        $typeStats = Notification::where('created_at', '>=', $cutoffDate)
            ->selectRaw('recipient_type, status, COUNT(*) as count')
            ->groupBy('recipient_type', 'status')
            ->get()
            ->groupBy('recipient_type');

        foreach ($typeStats as $type => $statusGroups) {
            $total = $statusGroups->sum('count');
            $sent = $statusGroups->where('status', 'sent')->sum('count');
            $failed = $statusGroups->where('status', 'failed')->sum('count');
            $successRate = $total > 0 ? round(($sent / $total) * 100, 1) : 0;

            $this->line("  • {$type}: {$total} total, {$sent} sent, {$failed} failed ({$successRate}% success)");
        }

        // Recent failures
        $recentFailures = Notification::where('status', 'failed')
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->count();

        if ($recentFailures > 0) {
            $this->newLine();
            $this->warn("⚠️  {$recentFailures} notifications failed in the last 24 hours");
        }

        // Queue backlog
        $pendingCount = Notification::where('status', 'pending')->count();
        if ($pendingCount > 0) {
            $this->warn("⏳ {$pendingCount} notifications pending in queue");
        }

        return 0;
    }

    /**
     * Reset failed notifications (mark as cancelled)
     */
    private function resetFailed(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🔄 Finding failed notifications to reset...");

        $failedNotifications = Notification::where('status', 'failed')
            ->where('retry_count', '>=', Notification::MAX_RETRY_ATTEMPTS)
            ->get();

        if ($failedNotifications->isEmpty()) {
            $this->info("✅ No failed notifications found to reset.");
            return 0;
        }

        $this->info("Found {$failedNotifications->count()} permanently failed notifications:");

        if ($dryRun) {
            $this->info("🔍 DRY RUN: Would reset {$failedNotifications->count()} notifications to cancelled status");
            return 0;
        }

        if (!$force && !$this->confirm("Do you want to reset these {$failedNotifications->count()} notifications to cancelled status?")) {
            $this->info("❌ Operation cancelled.");
            return 0;
        }

        $resetCount = $failedNotifications->each(function ($notification) {
            $notification->markAsCancelled();
        })->count();

        $this->info("✅ Reset completed: {$resetCount} notifications marked as cancelled");

        Log::info('Failed notifications reset completed', [
            'reset_count' => $resetCount,
            'executed_by' => 'command'
        ]);

        return 0;
    }
}
