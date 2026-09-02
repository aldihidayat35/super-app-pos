<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageHeaderTest extends TestCase
{
    #[Test]
    public function page_title_component_memindahkan_caption_dan_default_action_ke_header(): void
    {
        $html = Blade::render(<<<'BLADE'
            @extends('layouts.metronic.app')
            @section('title', 'Uji Page Header')
            @section('page_title', 'Judul Utama')
            @section('content')
                <x-metronic.page-title title="Judul Content Lama" description="Caption halaman & detail unik.">
                    <a href="/aksi" id="aksi-header" class="btn btn-primary">Jalankan</a>
                </x-metronic.page-title>
                <div id="isi-utama">Isi utama</div>
            @endsection
        BLADE, ['errors' => new ViewErrorBag]);

        $xpath = $this->xpath($html);

        $this->assertSame('Judul Utama', $this->nodeText($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__title ")]'));
        $this->assertSame('Caption halaman & detail unik.', $this->nodeText($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__description ")]'));
        $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__actions ")]//*[@id="aksi-header"]'));
        $this->assertCount(0, $xpath->query('//h1[normalize-space(.)="Judul Content Lama"] | //h2[normalize-space(.)="Judul Content Lama"]'));
        $this->assertCount(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " breadcrumb ")]'));
    }

    #[Test]
    public function page_title_component_menyediakan_fallback_title_dan_named_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            @extends('layouts.metronic.app')
            @section('title', 'Uji Fallback Header')
            @section('content')
                <x-metronic.page-title title="Judul Fallback" description="Caption fallback.">
                    <x-slot:actions>
                        <button type="button" id="named-action" class="btn btn-primary">Action</button>
                    </x-slot:actions>
                </x-metronic.page-title>
                <div id="isi-utama">Isi utama</div>
            @endsection
        BLADE, ['errors' => new ViewErrorBag]);

        $xpath = $this->xpath($html);

        $this->assertSame('Judul Fallback', $this->nodeText($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__title ")]'));
        $this->assertSame('Caption fallback.', $this->nodeText($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__description ")]'));
        $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__actions ")]//*[@id="named-action"]'));
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);

        return new \DOMXPath($dom);
    }

    private function nodeText(\DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        $this->assertCount(1, $nodes);

        return trim($nodes->item(0)->textContent);
    }
}
