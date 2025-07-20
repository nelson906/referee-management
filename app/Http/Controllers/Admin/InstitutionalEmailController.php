<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InstitutionalEmailController extends Controller
{
    /**
     * Display a listing of institutional emails.
     */
    public function index()
    {
        $emails = InstitutionalEmail::with('zone')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('admin.institutional-emails.index', compact('emails'));
    }

    /**
     * Show the form for creating a new institutional email.
     */
    public function create()
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $categories = InstitutionalEmail::getCategories();
        $notificationTypes = InstitutionalEmail::getNotificationTypes();

        return view('admin.institutional-emails.create', compact('zones', 'categories', 'notificationTypes'));
    }

    /**
     * Store a newly created institutional email.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutional_emails,email',
            'description' => 'nullable|string',
            'zone_id' => 'nullable|exists:zones,id',
            'category' => 'required|in:' . implode(',', array_keys(InstitutionalEmail::getCategories())),
            'is_active' => 'boolean',
            'receive_all_notifications' => 'boolean',
            'notification_types' => 'nullable|array',
            'notification_types.*' => 'in:' . implode(',', array_keys(InstitutionalEmail::getNotificationTypes())),
        ]);

        // Se receive_all_notifications è true, non serve specificare i tipi
        if ($validated['receive_all_notifications'] ?? false) {
            $validated['notification_types'] = [];
        }

        InstitutionalEmail::create($validated);

        return redirect()->route('institutional-emails.index')
            ->with('success', 'Email istituzionale creata con successo.');
    }

    /**
     * Display the specified institutional email.
     */
    public function show(InstitutionalEmail $email)
    {
        $email->load('zone');
        return view('admin.institutional-emails.show', compact('email'));
    }

    /**
     * Show the form for editing the institutional email.
     */
    public function edit(InstitutionalEmail $email)
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $categories = InstitutionalEmail::getCategories();
        $notificationTypes = InstitutionalEmail::getNotificationTypes();

        return view('admin.institutional-emails.edit', compact('email', 'zones', 'categories', 'notificationTypes'));
    }

    /**
     * Update the specified institutional email.
     */
    public function update(Request $request, InstitutionalEmail $email)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutional_emails,email,' . $email->id,
            'description' => 'nullable|string',
            'zone_id' => 'nullable|exists:zones,id',
            'category' => 'required|in:' . implode(',', array_keys(InstitutionalEmail::getCategories())),
            'is_active' => 'boolean',
            'receive_all_notifications' => 'boolean',
            'notification_types' => 'nullable|array',
            'notification_types.*' => 'in:' . implode(',', array_keys(InstitutionalEmail::getNotificationTypes())),
        ]);

        // Se receive_all_notifications è true, non serve specificare i tipi
        if ($validated['receive_all_notifications'] ?? false) {
            $validated['notification_types'] = [];
        }

        $email->update($validated);

        return redirect()->route('institutional-emails.index')
            ->with('success', 'Email istituzionale aggiornata con successo.');
    }

    /**
     * Remove the specified institutional email.
     */
    public function destroy(InstitutionalEmail $email)
    {
        $email->delete();

        return redirect()->route('institutional-emails.index')
            ->with('success', 'Email istituzionale eliminata con successo.');
    }

    /**
     * Toggle email active status.
     */
    public function toggleActive(InstitutionalEmail $email)
    {
        $email->update(['is_active' => !$email->is_active]);

        return response()->json([
            'success' => true,
            'message' => $email->is_active ? 'Email attivata.' : 'Email disattivata.',
            'is_active' => $email->is_active
        ]);
    }

    /**
     * Test email connectivity.
     */
    public function test(Request $request, InstitutionalEmail $email)
    {
        $validated = $request->validate([
            'test_subject' => 'required|string|max:255',
            'test_message' => 'required|string',
        ]);

        try {
            // Invia email di test
            Mail::raw($validated['test_message'], function ($message) use ($email, $validated) {
                $message->to($email->email, $email->name)
                        ->subject($validated['test_subject'])
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return response()->json([
                'success' => true,
                'message' => 'Email di test inviata con successo a ' . $email->email
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio dell\'email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations on institutional emails.
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'emails' => 'required|array',
            'emails.*' => 'exists:institutional_emails,id',
        ]);

        $emails = InstitutionalEmail::whereIn('id', $validated['emails']);

        switch ($validated['action']) {
            case 'activate':
                $emails->update(['is_active' => true]);
                $message = 'Email selezionate attivate con successo.';
                break;

            case 'deactivate':
                $emails->update(['is_active' => false]);
                $message = 'Email selezionate disattivate con successo.';
                break;

            case 'delete':
                $emails->delete();
                $message = 'Email selezionate eliminate con successo.';
                break;
        }

        return redirect()->route('institutional-emails.index')
            ->with('success', $message);
    }

    /**
     * Export institutional emails.
     */
    public function export()
    {
        $emails = InstitutionalEmail::with('zone')->get();

        $filename = 'institutional_emails_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($emails) {
            $file = fopen('php://output', 'w');

            // Header CSV
            fputcsv($file, [
                'Nome',
                'Email',
                'Categoria',
                'Zona',
                'Descrizione',
                'Stato',
                'Riceve Tutte',
                'Tipi Notifica',
                'Creato'
            ]);

            // Dati
            foreach ($emails as $email) {
                fputcsv($file, [
                    $email->name,
                    $email->email,
                    $email->category_display,
                    $email->zone?->name ?? 'Tutte',
                    $email->description,
                    $email->is_active ? 'Attivo' : 'Inattivo',
                    $email->receive_all_notifications ? 'Sì' : 'No',
                    implode(', ', $email->notification_types ?? []),
                    $email->created_at->format('d/m/Y H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
