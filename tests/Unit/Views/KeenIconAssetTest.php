<?php

namespace Tests\Unit\Views;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class KeenIconAssetTest extends TestCase
{
    #[Test]
    public function every_blade_icon_has_a_local_svg_mapping(): void
    {
        $styles = file_get_contents($this->projectPath('public/assets/css/ki-icons-fallback.css'));

        self::assertIsString($styles);

        $icons = [];

        foreach ($this->bladeFiles() as $file) {
            $content = file_get_contents($file->getPathname());

            self::assertIsString($content);
            preg_match_all('/(?<![A-Za-z0-9-])ki-[a-z0-9-]+/', $content, $matches);

            foreach ($matches[0] as $icon) {
                if (! in_array($icon, ['ki-outline', 'ki-solid', 'ki-duotone', 'ki-icons-fallback'], true)) {
                    $icons[$icon] = true;
                }
            }
        }

        $missing = array_values(array_filter(
            array_keys($icons),
            static fn (string $icon): bool => preg_match('/i\\.'.preg_quote($icon, '/').'(?=[\\s,{])/', $styles) !== 1,
        ));

        sort($missing);

        self::assertGreaterThan(100, count($icons), 'Audit ikon tidak menemukan seluruh kelas ikon aplikasi.');
        self::assertSame([], $missing, "Kelas KeenIcons berikut belum memiliki pemetaan SVG lokal:\n".implode("\n", $missing));
    }

    #[Test]
    public function every_application_layout_loads_the_icon_fallback(): void
    {
        foreach ([
            'resources/views/layouts/metronic/app.blade.php',
            'resources/views/layouts/metronic/auth.blade.php',
            'resources/views/layout-main/app.blade.php',
        ] as $layout) {
            $content = file_get_contents($this->projectPath($layout));

            self::assertIsString($content);
            self::assertStringContainsString("versioned_asset('assets/css/ki-icons-fallback.css')", $content, $layout);
            self::assertStringNotContainsString("filemtime(public_path('assets/css/ki-icons-fallback.css'))", $content, $layout);
        }
    }

    /** @return list<SplFileInfo> */
    private function bladeFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectPath('resources/views')),
        );
        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function projectPath(string $path = ''): string
    {
        return dirname(__DIR__, 3).($path === '' ? '' : DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}
