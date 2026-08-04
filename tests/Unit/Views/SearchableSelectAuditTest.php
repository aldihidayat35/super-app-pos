<?php

namespace Tests\Unit\Views;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class SearchableSelectAuditTest extends TestCase
{
    #[Test]
    public function every_application_select_uses_the_global_searchable_select_contract(): void
    {
        $violations = [];
        $selectCount = 0;

        foreach ($this->bladeFiles() as $file) {
            $content = file_get_contents($file->getPathname());

            self::assertIsString($content);

            foreach ($this->selectStartTags($content) as $tag) {
                $selectCount++;

                $usesFormSelect = preg_match('/\bclass\s*=\s*(["\'])(?:(?!\1).)*\bform-select\b(?:(?!\1).)*\1/is', $tag) === 1;
                $explicitlyNative = preg_match('/\bdata-control\s*=\s*(["\'])native\1/i', $tag) === 1;

                if (! $usesFormSelect && ! $explicitlyNative) {
                    $violations[] = $this->relativePath($file).': '.$tag;
                }
            }
        }

        self::assertGreaterThan(200, $selectCount, 'Audit tidak menemukan seluruh combobox aplikasi.');
        self::assertSame([], $violations, "Combobox berikut belum memakai Select2 global atau opt-out eksplisit:\n".implode("\n", $violations));
    }

    #[Test]
    public function global_select2_initializer_covers_static_and_dynamic_form_selects(): void
    {
        $script = file_get_contents($this->projectPath('resources/js/app.js'));

        self::assertIsString($script);
        self::assertStringContainsString('select.form-select:not([data-control="native"]):not([data-searchable="false"])', $script);
        self::assertStringContainsString('minimumResultsForSearch: 0', $script);
        self::assertStringContainsString('new MutationObserver', $script);
        self::assertStringContainsString("noResults: () => 'Data tidak ditemukan.'", $script);
    }

    #[Test]
    public function keen_icons_have_a_visible_local_svg_fallback(): void
    {
        $styles = file_get_contents($this->projectPath('public/assets/css/ki-icons-fallback.css'));

        self::assertIsString($styles);
        self::assertStringContainsString('--ki-svg-default: url(', $styles);
        self::assertStringContainsString('var(--ki-mask, var(--ki-svg-default))', $styles);
        self::assertStringContainsString('i.ki-trash { --ki-mask: var(--ki-svg-trash); }', $styles);
        self::assertStringContainsString('background-color: currentColor', $styles);
        self::assertStringNotContainsString('content: "•"', $styles);
        self::assertStringNotContainsString('content: "â€¢"', $styles);
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

    /** @return list<string> */
    private function selectStartTags(string $content): array
    {
        $tags = [];
        $offset = 0;
        $length = strlen($content);

        while (preg_match('/<select\b/i', $content, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1];
            $quote = null;

            for ($position = $start; $position < $length; $position++) {
                $character = $content[$position];

                if ($quote === null && ($character === '"' || $character === "'")) {
                    $quote = $character;
                } elseif ($quote === $character) {
                    $quote = null;
                } elseif ($quote === null && $character === '>') {
                    $tags[] = preg_replace('/\s+/', ' ', substr($content, $start, $position - $start + 1)) ?: '';
                    $offset = $position + 1;

                    continue 2;
                }
            }

            break;
        }

        return $tags;
    }

    private function projectPath(string $path = ''): string
    {
        return dirname(__DIR__, 3).($path === '' ? '' : DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    private function relativePath(SplFileInfo $file): string
    {
        return str_replace('\\', '/', substr($file->getPathname(), strlen($this->projectPath()) + 1));
    }
}
