<?php

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmptyStateComponentTest extends TestCase
{
    #[Test]
    public function it_renders_empty_state_with_flex_centering_and_custom_props(): void
    {
        $rendered = (string) $this->blade(
            '<x-metronic.empty-state title="Belum ada transaksi ditemukan" description="Tidak ada transaksi POS yang sesuai." icon="ki-outline ki-basket" />'
        );

        $this->assertStringContainsString('d-flex flex-column align-items-center justify-content-center text-center', $rendered);
        $this->assertStringContainsString('ki-outline ki-basket', $rendered);
        $this->assertStringContainsString('Belum ada transaksi ditemukan', $rendered);
        $this->assertStringContainsString('Tidak ada transaksi POS yang sesuai.', $rendered);
        $this->assertStringContainsString('d-flex align-items-center justify-content-center mb-4', $rendered);
    }

    #[Test]
    public function it_renders_empty_state_slot_properly(): void
    {
        $rendered = (string) $this->blade(
            '<x-metronic.empty-state><button class="btn btn-primary">Tambah Data</button></x-metronic.empty-state>'
        );

        $this->assertStringContainsString('Tambah Data', $rendered);
        $this->assertStringContainsString('d-flex flex-column align-items-center justify-content-center', $rendered);
    }
}
