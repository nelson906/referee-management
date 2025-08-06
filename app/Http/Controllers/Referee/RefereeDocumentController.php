<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class RefereeDocumentController extends Controller
{
    public function index()
    {
        // Mostra solo documenti pubblici o della sua zona
        $documents = Document::where('is_public', true)
            ->orWhere('zone_id', auth()->user()->zone_id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('referee.documents.index', compact('documents'));
    }

    public function download(Document $document)
    {
        // Verifica accesso
        if (!$document->is_public && $document->zone_id !== auth()->user()->zone_id) {
            abort(403, 'Non autorizzato');
        }

        return Storage::download($document->file_path, $document->title . '.pdf');
    }
}
