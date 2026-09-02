<?php

namespace Tests\Unit\Views;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ResponsiveMobileTableTest extends TestCase
{
    #[Test]
    public function global_initializer_covers_all_application_tables_and_ajax_updates(): void
    {
        $application = $this->read('resources/js/app.js');
        $enhancer = $this->read('resources/js/modules/responsive-mobile-tables.js');

        self::assertStringContainsString('import { initializeResponsiveTables, refreshResponsiveTable }', $application);
        self::assertStringContainsString('initializeResponsiveTables();', $application);
        self::assertStringContainsString('window.GudangTokoResponsiveTables', $application);
        self::assertStringContainsString('table:not([data-mobile-table="off"])', $enhancer);
        self::assertStringContainsString('new MutationObserver', $enhancer);
        self::assertStringContainsString('mutation.addedNodes.forEach', $enhancer);
        self::assertStringContainsString('currentTrigger.dataset.mobileSignature', $enhancer);
    }

    #[Test]
    public function mobile_cards_are_accessible_and_use_single_open_accordion_by_default(): void
    {
        $enhancer = $this->read('resources/js/modules/responsive-mobile-tables.js');

        self::assertStringContainsString("trigger.type = 'button'", $enhancer);
        self::assertStringContainsString("trigger.setAttribute('aria-expanded'", $enhancer);
        self::assertStringContainsString("trigger.setAttribute('aria-controls'", $enhancer);
        self::assertStringContainsString("table.dataset.mobileAccordion !== 'multiple'", $enhancer);
        self::assertStringContainsString("table.querySelectorAll('tbody tr.is-mobile-expanded')", $enhancer);
        self::assertStringContainsString('Array.from(cell.childNodes)', $enhancer, 'Kontrol asli harus dipindah, bukan digandakan.');
        self::assertStringContainsString('markSummaryDuplicates(row, cells, columns)', $enhancer);
        self::assertStringContainsString("cell.classList.add('gt-mobile-summary-duplicate')", $enhancer);
    }

    #[Test]
    public function responsive_styles_switch_only_below_bootstrap_tablet_breakpoint(): void
    {
        $styles = $this->read('resources/css/app.css');

        self::assertStringContainsString('@media (max-width: 767.98px)', $styles);
        self::assertStringContainsString('table.gt-mobile-ready > tbody > tr', $styles);
        self::assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $styles);
        self::assertStringContainsString('overflow-x: hidden !important', $styles);
        self::assertStringContainsString('#kt_app_wrapper {', $styles);
        self::assertStringContainsString('margin-top: 0 !important', $styles);
        self::assertStringContainsString('#kt_app_content_container .card > .card-body', $styles);
        self::assertStringContainsString('padding: 0.75rem !important', $styles);
        self::assertStringContainsString(".gt-mobile-card-trigger[aria-expanded='true']", $styles);
        self::assertStringContainsString('table.gt-mobile-ready > tbody > tr.is-mobile-expanded', $styles);
        self::assertStringContainsString('border-color: rgba(var(--bs-primary-rgb), 0.65)', $styles);
        self::assertStringContainsString('outline-color: rgba(var(--bs-primary-rgb), 0.65)', $styles);
        self::assertStringContainsString('0 0 0 0.16rem rgba(var(--bs-primary-rgb), 0.13)', $styles);
        self::assertStringContainsString('tr:not(.is-mobile-expanded) > td.gt-mobile-summary-host > .gt-mobile-cell-value', $styles);
        self::assertStringContainsString('td.gt-mobile-summary-duplicate:not(.gt-mobile-summary-host)', $styles);
        self::assertStringContainsString('.gt-mobile-cell-value .gt-mobile-summary-source', $styles);

        $firstBreakpoint = strpos($styles, '@media (max-width: 767.98px)');
        $cardRule = strpos($styles, 'table.gt-mobile-ready {');

        self::assertNotFalse($firstBreakpoint);
        self::assertNotFalse($cardRule);
        self::assertGreaterThan($firstBreakpoint, $cardRule, 'Transformasi tabel menjadi kartu tidak boleh aktif pada desktop.');
    }

    #[Test]
    public function application_does_not_mix_bundled_datatables_with_cdn_versions(): void
    {
        $violations = [];
        $tableCount = 0;

        foreach ($this->bladeFiles() as $path) {
            $content = $this->read($path);
            $tableCount += preg_match_all('/<table\b/i', $content);

            if (stripos($content, 'cdn.datatables.net') !== false) {
                $violations[] = $path;
            }
        }

        self::assertGreaterThan(120, $tableCount, 'Audit harus mencakup seluruh tabel aplikasi.');
        self::assertSame([], $violations, 'DataTables wajib memakai bundle Vite lokal: '.implode(', ', $violations));
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $root = $this->projectPath('resources/views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $paths = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($this->projectPath()) + 1));
            }
        }

        return $paths;
    }

    private function read(string $path): string
    {
        $content = file_get_contents($this->projectPath($path));

        self::assertIsString($content, "Gagal membaca {$path}.");

        return $content;
    }

    private function projectPath(string $path = ''): string
    {
        return dirname(__DIR__, 3).($path === '' ? '' : DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}
