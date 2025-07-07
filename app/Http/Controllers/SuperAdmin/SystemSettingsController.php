<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    /**
     * Display system settings.
     */
    public function index(): View
    {
        return view('super-admin.settings.index', [
            'title' => 'Impostazioni Sistema'
        ]);
    }

    /**
     * Update system settings.
     */
    public function update(Request $request)
    {
        return redirect()->route('super-admin.settings.index')
            ->with('success', 'Impostazioni aggiornate con successo!');
    }
}
