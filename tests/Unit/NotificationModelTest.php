<?php
// File: tests/Unit/NotificationModelTest.php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Notification;
use App\Models\Assignment;
use App\Models\User;
use App\Models\Tournament;

class NotificationModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function notification_can_be_marked_as_sent()
    {
        $notification = Notification::factory()->create(['status' => 'pending']);

        $notification->markAsSent();

        $this->assertEquals('sent', $notification->status);
        $this->assertNotNull($notification->sent_at);
        $this->assertNull($notification->error_message);
    }

    #[Test]
    public function notification_can_be_marked_as_failed()
    {
        $notification = Notification::factory()->create(['status' => 'pending']);

        $notification->markAsFailed('Test error message');

        $this->assertEquals('failed', $notification->status);
        $this->assertEquals('Test error message', $notification->error_message);
        $this->assertEquals(1, $notification->retry_count);
    }

    #[Test]
    public function notification_can_check_retry_eligibility()
    {
        $notification = Notification::factory()->create([
            'status' => 'failed',
            'retry_count' => 1
        ]);

        $this->assertTrue($notification->canBeRetried());

        $notification->retry_count = Notification::MAX_RETRY_ATTEMPTS;
        $this->assertFalse($notification->canBeRetried());
    }

    #[Test]
    public function notification_calculates_priority_correctly()
    {
        $assignment = Assignment::factory()->create();
        $notification = Notification::factory()->create([
            'assignment_id' => $assignment->id,
            'recipient_type' => 'referee'
        ]);

        $priority = $notification->priority;

        $this->assertIsInt($priority);
        $this->assertGreaterThanOrEqual(0, $priority);
    }

    #[Test]
    public function notification_has_correct_status_labels()
    {
        $notification = Notification::factory()->create(['status' => 'sent']);
        $this->assertEquals('Inviata', $notification->status_label);

        $notification->status = 'pending';
        $this->assertEquals('In Sospeso', $notification->status_label);

        $notification->status = 'failed';
        $this->assertEquals('Fallita', $notification->status_label);
    }
}
