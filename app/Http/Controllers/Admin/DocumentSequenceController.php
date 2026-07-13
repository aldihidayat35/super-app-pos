<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDocumentSequencesRequest;
use App\Models\DocumentSequence;
use App\Models\SystemSetting;
use App\Services\Organization\DocumentNumberService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DocumentSequenceController extends Controller
{
    public function index(DocumentNumberService $numberService): View
    {
        $this->authorize('view', SystemSetting::class);
        $this->ensureGlobalSequences();

        $sequences = DocumentSequence::query()
            ->where('scope_key', 'global')
            ->where('year', (int) now()->format('Y'))
            ->orderBy('document_type')
            ->get();

        return view('admin.settings.document-numbers', [
            'sequences' => $sequences,
            'numberService' => $numberService,
        ]);
    }

    public function update(UpdateDocumentSequencesRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('sequences') as $sequenceData) {
                DocumentSequence::query()->updateOrCreate(
                    [
                        'document_type' => $sequenceData['document_type'],
                        'location_type' => null,
                        'location_id' => null,
                        'scope_key' => 'global',
                        'year' => (int) now()->format('Y'),
                    ],
                    [
                        'prefix' => $sequenceData['prefix'],
                        'next_number' => $sequenceData['next_number'],
                        'padding' => $sequenceData['padding'],
                        'reset_yearly' => (bool) ($sequenceData['reset_yearly'] ?? false),
                        'format' => $sequenceData['format'],
                    ],
                );
            }

            activity()->causedBy($request->user())->log('admin.settings.document_numbers_updated');
        });

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Konfigurasi nomor dokumen berhasil disimpan.',
        ]);
    }

    private function ensureGlobalSequences(): void
    {
        foreach (DocumentNumberService::DEFAULT_PREFIXES as $type => $prefix) {
            DocumentSequence::query()->firstOrCreate(
                [
                    'document_type' => $type,
                    'location_type' => null,
                    'location_id' => null,
                    'scope_key' => 'global',
                    'year' => (int) now()->format('Y'),
                ],
                [
                    'prefix' => $prefix,
                    'next_number' => 1,
                    'padding' => 5,
                    'reset_yearly' => true,
                    'format' => '{prefix}/{location}/{year}/{sequence}',
                ],
            );
        }
    }
}
