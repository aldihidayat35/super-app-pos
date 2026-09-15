<?php

namespace Tests\Unit\Views;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResponsiveMobileLayoutTest extends TestCase
{
    #[Test]
    public function mobile_layout_standard_covers_shared_application_components(): void
    {
        $styles = $this->read('resources/css/app.css');
        $mobileStart = strpos($styles, '@media (max-width: 767.98px)');

        self::assertNotFalse($mobileStart);

        $mobileStyles = substr($styles, $mobileStart);

        foreach ([
            '--gt-mobile-gutter',
            '#kt_app_toolbar_container',
            '#kt_app_content_container',
            '.form-control',
            '.form-select',
            '.select2-container',
            '.flatpickr-calendar',
            '.gt-table-toolbar',
            '.dt-container > .d-flex',
            '.dt-container .dt-layout-row',
            '.pagination',
            '.nav-tabs',
            '.modal-dialog:not(.modal-fullscreen)',
            '.offcanvas-start',
            '.swal2-popup',
            '.dropdown-menu',
        ] as $selector) {
            self::assertStringContainsString($selector, $mobileStyles, "Standar mobile belum mencakup {$selector}.");
        }

        self::assertStringContainsString('min-height: var(--gt-mobile-control-height)', $mobileStyles);
        self::assertStringContainsString('width: 100% !important', $mobileStyles);
    }

    #[Test]
    public function table_toolbar_exposes_a_stable_responsive_hook(): void
    {
        $toolbar = $this->read('resources/views/components/metronic/table-toolbar.blade.php');

        self::assertStringContainsString('gt-table-toolbar', $toolbar);
        self::assertStringContainsString('d-flex flex-wrap gap-2', $toolbar);
    }

    private function read(string $path): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));

        self::assertIsString($content, "Gagal membaca {$path}.");

        return $content;
    }
}
