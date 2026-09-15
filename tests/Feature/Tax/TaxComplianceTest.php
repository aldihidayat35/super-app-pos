<?php

namespace Tests\Feature\Tax;

use App\Models\TaxDocument;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_dan_histori_pajak_lama_tetap_dapat_dibaca(): void
    {
        $period = TaxPeriod::create(['month' => 12, 'year' => 2025, 'status' => 'approved']);
        $document = TaxDocument::create([
            'tax_period_id' => $period->id,
            'direction' => 'output',
            'tax_type' => 'ppn',
            'document_type' => 'legacy',
            'document_number' => 'LEGACY-2025-001',
            'source_key' => 'legacy:1',
            'counterparty_name' => 'Pelanggan Lama',
            'issue_date' => '2025-12-31',
            'tax_date' => '2025-12-31',
            'dpp_amount' => 100000,
            'tax_rate' => 11,
            'dpp_factor' => 1,
            'tax_amount' => 11000,
            'total_amount' => 111000,
            'is_creditable' => false,
            'status' => 'posted',
            'reconciliation_status' => 'unmatched',
        ]);

        $this->assertSame('11000.00', $document->fresh()->tax_amount);
        $this->assertSame('approved', $period->fresh()->status->value);
        $this->assertDatabaseHas('tax_documents', ['document_number' => 'LEGACY-2025-001']);
    }

    public function test_profil_pajak_baru_dapat_disimpan_dalam_mode_non_pkp_tanpa_kalkulasi(): void
    {
        $profile = TaxProfile::create([
            'scope_key' => 'company',
            'legal_name' => 'PT Non PKP',
            'is_pkp' => false,
            'calculation_enabled' => false,
        ]);

        $this->assertFalse($profile->is_pkp);
        $this->assertFalse($profile->calculation_enabled);
    }
}
