<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstitutionalEmailController extends Controller
{
    /**
     * Display a listing of institutional emails.
     */
    public function index(Request $request)
    {
        $query = InstitutionalEmail::with('zone');

        // Search filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('zone_id')) {
            if ($request->zone_id === 'null') {
                $query->whereNull('zone_id');
            } else {
                $query->where('zone_id', $request->zone_id);
            }
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $institutionalEmails = $query->orderBy('name')->paginate(20);
        $zones = Zone::orderBy('name')->get();

        return view('super-admin.institutional-emails.index', compact('institutionalEmails', 'zones'));
    }

    /**
     * Show the form for creating a new institutional email.
     */
    public function create()
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $categories = InstitutionalEmail::CATEGORIES;
        $notificationTypes = InstitutionalEmail::NOTIFICATION_TYPES;

        return view('super-admin.institutional-emails.create', compact('zones', 'categories', 'notificationTypes'));
    }

    /**
     * Store a newly created institutional email.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:institutional_emails,email',
            'description' => 'nullable|string|max:1000',
            'category' => ['required', Rule::in(array_keys(InstitutionalEmail::CATEGORIES))],
            'zone_id' => 'nullable|exists:zones,id',
            'is_active' => 'boolean',
            'receive_all_notifications' => 'boolean',
            'notification_types' => 'nullable|array',
            'notification_types.*' => Rule::in(array_keys(InstitutionalEmail::NOTIFICATION_TYPES)),
        ]);

        $data = $request->all();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['receive_all_notifications'] = $request->boolean('receive_all_notifications', false);

        // If receive_all_notifications is true, clear specific notification_types
        if ($data['receive_all_notifications']) {
            $data['notification_types'] = null;
        }

        InstitutionalEmail::create($data);

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', 'Email istituzionale creata con successo.');
    }

    /**
     * Display the specified institutional email.
     */
    public function show(InstitutionalEmail $institutionalEmail)
    {
        $institutionalEmail->load('zone');
        return view('super-admin.institutional-emails.show', compact('institutionalEmail'));
    }

    /**
     * Show the form for editing the specified institutional email.
     */
    public function edit(InstitutionalEmail $institutionalEmail)
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $categories = InstitutionalEmail::CATEGORIES;
        $notificationTypes = InstitutionalEmail::NOTIFICATION_TYPES;

        return view('super-admin.institutional-emails.edit', compact('institutionalEmail', 'zones', 'categories', 'notificationTypes'));
    }

    /**
     * Update the specified institutional email.
     */
    public function update(Request $request, InstitutionalEmail $institutionalEmail)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('institutional_emails', 'email')->ignore($institutionalEmail->id)],
            'description' => 'nullable|string|max:1000',
            'category' => ['required', Rule::in(array_keys(InstitutionalEmail::CATEGORIES))],
            'zone_id' => 'nullable|exists:zones,id',
            'is_active' => 'boolean',
            'receive_all_notifications' => 'boolean',
            'notification_types' => 'nullable|array',
            'notification_types.*' => Rule::in(array_keys(InstitutionalEmail::NOTIFICATION_TYPES)),
        ]);

        $data = $request->all();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['receive_all_notifications'] = $request->boolean('receive_all_notifications', false);

        // If receive_all_notifications is true, clear specific notification_types
        if ($data['receive_all_notifications']) {
            $data['notification_types'] = null;
        }

        $institutionalEmail->update($data);

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', 'Email istituzionale aggiornata con successo.');
    }

    /**
     * Remove the specified institutional email.
     */
    public function destroy(InstitutionalEmail $institutionalEmail)
    {
        try {
            $institutionalEmail->delete();

            return redirect()->route('super-admin.institutional-emails.index')
                ->with('success', 'Email istituzionale eliminata con successo.');
        } catch (\Exception $e) {
            return redirect()->route('super-admin.institutional-emails.index')
                ->with('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
    }

    /**
     * Export institutional emails to CSV.
     */
    public function export(Request $request)
    {
        $query = InstitutionalEmail::with('zone');

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('zone_id')) {
            if ($request->zone_id === 'null') {
                $query->whereNull('zone_id');
            } else {
                $query->where('zone_id', $request->zone_id);
            }
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $emails = $query->orderBy('name')->get();

        $filename = 'institutional_emails_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($emails) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'Nome',
                'Email',
                'Descrizione',
                'Categoria',
                'Zona',
                'Attivo',
                'Riceve Tutte le Notifiche',
                'Tipi di Notifica',
                'Creato il',
                'Aggiornato il'
            ]);

            foreach ($emails as $email) {
                fputcsv($file, [
                    $email->name,
                    $email->email,
                    $email->description,
                    $email->category_label,
                    $email->zone ? $email->zone->name : 'Tutte le Zone',
                    $email->is_active ? 'Sì' : 'No',
                    $email->receive_all_notifications ? 'Sì' : 'No',
                    $email->notification_types ? implode(', ', array_map(function($type) {
                        return InstitutionalEmail::NOTIFICATION_TYPES[$type] ?? $type;
                    }, $email->notification_types)) : '',
                    $email->created_at->format('d/m/Y H:i'),
                    $email->updated_at->format('d/m/Y H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk actions on institutional emails.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'email_ids' => 'required|array|min:1',
            'email_ids.*' => 'exists:institutional_emails,id'
        ]);

        $emails = InstitutionalEmail::whereIn('id', $request->email_ids);

        switch ($request->action) {
            case 'activate':
                $emails->update(['is_active' => true]);
                $message = 'Email istituzionali attivate con successo.';
                break;

            case 'deactivate':
                $emails->update(['is_active' => false]);
                $message = 'Email istituzionali disattivate con successo.';
                break;

            case 'delete':
                $emails->delete();
                $message = 'Email istituzionali eliminate con successo.';
                break;
        }

        return redirect()->route('super-admin.institutional-emails.index')
            ->with('success', $message);
    }
}
