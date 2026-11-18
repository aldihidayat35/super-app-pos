<?php

namespace Tests\Feature\Tax;

use App\Enums\FinancialMonthStatus;
use App\Enums\FinancialYearStatus;
use App\Enums\IncomeTaxScheme;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Finance\AnnualFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnnualFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_historis_menghasilkan_laba_rugi_dan_neraca_seperti_contoh_owner(): void
    {
        $user = User::factory()->create();
        $service = app(AnnualFinanceService::class);
        $year = $service->createYear(['year' => 2025, 'legal_name' => 'PT KPM', 'is_historical' => true], $user);
        $december = $year->months->firstWhere('month', 12);

        foreach ([
            ['sales_adjustment', '4542453500'], ['cogs_adjustment', '4092300500'],
            ['salary_expense', '216000000'], ['rent_expense', '10000000'], ['utilities_expense', '5000000'],
            ['cash_bank', '115500000'], ['receivable_adjustment', '79500000'], ['inventory_adjustment', '5312007570'],
            ['accounts_payable', '4561961400'], ['paid_in_capital', '750000000'],
        ] as [$account, $amount]) {
            $service->addEntry($december, ['account_code' => $account, 'amount' => $amount, 'reason' => 'Saldo laporan owner 2025'], $user);
        }
        $year->update([
            'tax_scheme' => IncomeTaxScheme::ARTICLE_31E, 'tax_scheme_confirmed' => true,
            'report_city' => 'Jakarta', 'commissioner_name' => 'Komisaris', 'director_name' => 'Direktur',
        ]);
        $year->months()->update(['status' => FinancialMonthStatus::LOCKED]);

        $report = $service->report($year->fresh('months.entries'));
        $this->assertSame('219153000.00', $report['profit']);
        $this->assertSame('24106830.00', $report['tax']);
        $this->assertSame('195046170.00', $report['netProfit']);
        $this->assertSame('5507007570.00', $report['assets']);
        $this->assertSame('0.00', $report['balance_difference']);

        $service->yearTransition($year->fresh(), 'review', $user);
        $service->yearTransition($year->fresh(), 'approve', $user);
        $service->yearTransition($year->fresh(), 'lock', $user);
        $this->assertSame('locked', $year->fresh()->status->value);
    }

    public function test_laporan_tidak_dapat_disetujui_jika_neraca_tidak_seimbang(): void
    {
        $user = User::factory()->create();
        $service = app(AnnualFinanceService::class);
        $year = $service->createYear(['year' => 2025, 'legal_name' => 'PT Uji'], $user);
        $year->months()->update(['status' => FinancialMonthStatus::LOCKED]);
        $year->update(['tax_scheme_confirmed' => true, 'report_city' => 'Jakarta', 'commissioner_name' => 'A', 'director_name' => 'B']);
        $service->addEntry($year->months->first(), ['account_code' => 'cash_bank', 'amount' => '1000', 'reason' => 'Saldo pembuka kas'], $user);
        $service->yearTransition($year->fresh(), 'review', $user);

        $this->expectException(ValidationException::class);
        $service->yearTransition($year->fresh(), 'approve', $user);
    }

    public function test_owner_hanya_dapat_melihat_dan_mengunduh(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('owner_viewer'));
        $year = app(AnnualFinanceService::class)->createYear(['year' => 2025, 'legal_name' => 'PT Uji'], $owner);

        $this->actingAs($owner)->get(route('tax.index', ['year' => 2025]))->assertOk()->assertSee('Keuangan &amp; Pajak Tahunan', false);
        $this->actingAs($owner)->put(route('tax.years.update', $year), [])->assertForbidden();
        $this->actingAs($owner)->get(route('tax.years.pdf', $year))->assertOk();
    }

    public function test_staf_keuangan_dapat_membuat_tahun_dan_endpoint_lama_tidak_tersedia(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(Role::findByName('staf_keuangan'));

        $this->actingAs($staff)->post(route('tax.years.store'), ['year' => 2024, 'legal_name' => 'PT Historis', 'is_historical' => 1])->assertRedirect();
        $this->assertDatabaseHas('financial_years', ['year' => 2024, 'is_historical' => true]);
        $this->assertDatabaseCount('financial_monthly_closings', 12);
        $this->actingAs($staff)->get('/tax/settings')->assertNotFound();
    }

    public function test_semua_skema_pph_menggunakan_tarif_dan_batas_omzet_tahun_laporan(): void
    {
        $user = User::factory()->create();
        $service = app(AnnualFinanceService::class);
        $cases = [
            [2021, IncomeTaxScheme::FINAL_05, '4000000000', '3900000000', null, '20000000.00'],
            [2022, IncomeTaxScheme::ARTICLE_31E, '10000000000', '9900000000', null, '16720000.00'],
            [2023, IncomeTaxScheme::ARTICLE_31E, '60000000000', '59900000000', null, '22000000.00'],
            [2024, IncomeTaxScheme::GENERAL, '1000000000', '900000000', null, '22000000.00'],
            [2026, IncomeTaxScheme::MANUAL, '1000000000', '900000000', '12345678', '12345678.00'],
        ];

        foreach ($cases as [$yearNumber, $scheme, $sales, $cogs, $manual, $expected]) {
            $year = $service->createYear(['year' => $yearNumber, 'legal_name' => 'PT Uji'], $user);
            $month = $year->months->first();
            $service->addEntry($month, ['account_code' => 'sales_adjustment', 'amount' => $sales, 'reason' => 'Skenario pengujian omzet'], $user);
            $service->addEntry($month, ['account_code' => 'cogs_adjustment', 'amount' => $cogs, 'reason' => 'Skenario pengujian HPP'], $user);
            $year->update(['tax_scheme' => $scheme, 'manual_tax_amount' => $manual]);

            $this->assertSame($expected, $service->report($year->fresh('months.entries'))['tax'], "Skema {$scheme->value} tahun {$yearNumber}");
        }
    }

    public function test_snapshot_bulanan_kosong_idempoten_dan_menyimpan_waktu_pengambilan(): void
    {
        $user = User::factory()->create();
        $service = app(AnnualFinanceService::class);
        $year = $service->createYear(['year' => 2025, 'legal_name' => 'PT Uji'], $user);
        $month = $year->months->first();

        $service->snapshot($month, $user);
        $service->snapshot($month->fresh(), $user);

        $month->refresh();
        $this->assertSame('0.00', $month->auto_pos_sales_amount);
        $this->assertSame('0.00', $month->auto_b2b_sales_amount);
        $this->assertNotEmpty($month->source_snapshot['captured_at']);
        $this->assertSame($user->id, $month->prepared_by);
    }

    public function test_owner_dan_tim_pajak_dapat_mengubah_persentase_tarif_pajak_dan_laporan_dihitung_ulang(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('owner_viewer'));
        $service = app(AnnualFinanceService::class);
        $year = $service->createYear(['year' => 2025, 'legal_name' => 'PT Uji'], $owner);
        $month = $year->months->first();
        $service->addEntry($month, ['account_code' => 'sales_adjustment', 'amount' => '1000000000', 'reason' => 'Omzet pengujian'], $owner);
        $service->addEntry($month, ['account_code' => 'cogs_adjustment', 'amount' => '800000000', 'reason' => 'HPP pengujian'], $owner);

        $response = $this->actingAs($owner)->put(route('tax.years.rates.update', $year), [
            'final_rate' => '0.6',
            'facility_rate' => '10.5',
            'general_rate' => '20.0',
            'small_turnover_limit' => '5000000000',
            'facility_turnover_limit' => '55000000000',
            'manual_tax_amount' => null,
            'notes' => 'Penyesuaian tarif oleh Owner',
        ]);

        $response->assertRedirect(route('tax.index', ['year' => 2025, 'tab' => 'rates']));
        $year->refresh();
        $this->assertEquals('0.6000', $year->final_rate);
        $this->assertEquals('10.5000', $year->facility_rate);
        $this->assertEquals('20.0000', $year->general_rate);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'finance.year.tax_rates_updated',
            'module' => 'finance_annual',
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_tarif_pajak_tidak_dapat_diubah_jika_tahun_laporan_telah_dikunci(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('owner_viewer'));
        $service = app(AnnualFinanceService::class);
        $year = $service->createYear(['year' => 2025, 'legal_name' => 'PT Uji'], $owner);
        $year->update(['status' => FinancialYearStatus::LOCKED]);

        $response = $this->actingAs($owner)->put(route('tax.years.rates.update', $year), [
            'final_rate' => '0.6',
            'facility_rate' => '11.0',
            'general_rate' => '22.0',
            'small_turnover_limit' => '4800000000',
            'facility_turnover_limit' => '50000000000',
        ]);

        $response->assertSessionHasErrors('year');
    }

    public function test_pengguna_dapat_menambah_dan_toggle_aturan_tarif_transaksi(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('owner_viewer'));

        $response = $this->actingAs($owner)->post(route('tax.rules.store'), [
            'code' => 'PPN-12-TEST',
            'name' => 'PPN 12% Regulasi Baru',
            'tax_type' => 'ppn',
            'direction' => 'both',
            'rate' => '12',
            'dpp_factor' => '1.00000000',
            'effective_from' => now()->toDateString(),
            'is_creditable' => 1,
            'is_active' => 1,
            'notes' => 'Uji coba penambahan aturan PPN 12%',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tax_rules', [
            'code' => 'PPN-12-TEST',
            'rate' => 12,
            'is_active' => true,
        ]);

        $rule = TaxRule::where('code', 'PPN-12-TEST')->firstOrFail();
        $toggleResponse = $this->actingAs($owner)->post(route('tax.rules.toggle', $rule));
        $toggleResponse->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_auto_initialize_year_and_national_rates_when_accessing_empty_year(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('owner_viewer'));

        $this->assertDatabaseMissing('financial_years', ['year' => 2027]);

        $response = $this->actingAs($owner)->get(route('tax.index', ['year' => 2027]));
        $response->assertOk();
        $response->assertSee('Pusat Pajak');
        $response->assertSee('Kepatuhan SPT PPN 1111');
        $response->assertSee('Simulasi PPh Usaha');
        $response->assertSee('Konfigurasi Tarif Pajak');
        $response->assertDontSee('Belum ada tahun laporan');

        $this->assertDatabaseHas('financial_years', [
            'year' => 2027,
            'final_rate' => '0.50',
            'facility_rate' => '11.00',
            'general_rate' => '22.00',
        ]);
    }

    public function test_owner_tax_report_redirects_seamlessly_to_unified_tax_hub(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('owner_viewer'));

        $response = $this->actingAs($owner)->get('/reports/owner/tax?start_date=2026-11-01&end_date=2026-11-18');
        $response->assertRedirect(route('tax.index', [
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-18',
        ]));
    }
}
