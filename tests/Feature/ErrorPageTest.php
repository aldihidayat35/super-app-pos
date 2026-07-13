<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    #[Test]
    public function missing_page_uses_the_indonesian_metronic_error_view(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee('Kembali ke Login');
    }
}
