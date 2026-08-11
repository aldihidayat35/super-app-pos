<?php

namespace App\Services\Guide;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class RoleGuideService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function availableFor(User $user): array
    {
        $roleNames = $user->roles()->pluck('name')->map(fn (mixed $role): string => (string) $role)->all();
        $showAll = in_array('super_admin', $roleNames, true);
        $configuredGuides = config('role-guides.guides');

        if (! is_array($configuredGuides)) {
            return [];
        }

        $available = [];
        foreach ($configuredGuides as $slug => $guide) {
            if (! is_string($slug) || ! is_array($guide)) {
                continue;
            }

            $configuredRoles = $guide['roles'] ?? [];
            $roles = is_array($configuredRoles)
                ? array_values(array_filter($configuredRoles, is_string(...)))
                : [];

            if (! $showAll && ! in_array('*', $roles, true) && array_intersect($roles, $roleNames) === []) {
                continue;
            }

            $guide['slug'] = $slug;
            $guide['roles'] = $roles;
            $guide['matching_roles'] = array_values(array_intersect($roles, $roleNames));
            $available[$slug] = $guide;
        }

        return $available;
    }

    /**
     * @return array<string, mixed>
     */
    public function findFor(User $user, string $slug): array
    {
        $guide = $this->availableFor($user)[$slug] ?? null;
        abort_unless(is_array($guide), 403, 'Panduan ini tidak tersedia untuk role Anda.');

        return $guide;
    }

    /**
     * @param  array<string, mixed>  $guide
     * @return array{html: string, toc: list<array{level: int, title: string, id: string}>, reading_minutes: int}
     */
    public function render(array $guide): array
    {
        $relativePath = (string) ($guide['file'] ?? '');
        $path = base_path($relativePath);
        abort_unless($relativePath !== '' && File::isFile($path), 500, 'Dokumen panduan tidak ditemukan.');

        $markdown = File::get($path);
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $toc = [];
        $usedIds = [];

        $htmlWithAnchors = preg_replace_callback('/<h([2-4])>(.*?)<\/h\1>/s', function (array $matches) use (&$toc, &$usedIds): string {
            $level = (int) $matches[1];
            $title = trim(html_entity_decode(strip_tags($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $baseId = Str::slug($title) ?: 'bagian';
            $counter = ($usedIds[$baseId] ?? 0) + 1;
            $usedIds[$baseId] = $counter;
            $id = $counter === 1 ? $baseId : $baseId.'-'.$counter;
            $toc[] = ['level' => $level, 'title' => $title, 'id' => $id];

            return sprintf('<h%d id="%s" class="guide-anchor">%s</h%d>', $level, e($id), $matches[2], $level);
        }, $html) ?? $html;

        $wordCount = count(preg_split('/\s+/u', trim(strip_tags($htmlWithAnchors))) ?: []);

        return [
            'html' => $htmlWithAnchors,
            'toc' => $toc,
            'reading_minutes' => max(1, (int) ceil($wordCount / 180)),
        ];
    }

    /**
     * @return list<array{name: string, label: string, description: string}>
     */
    public function userRoles(User $user): array
    {
        $metadata = config('rbac.roles', []);

        return $user->roles()->pluck('name')->map(function (mixed $name) use ($metadata): array {
            $roleName = (string) $name;
            $role = $metadata[$roleName] ?? [];

            return [
                'name' => $roleName,
                'label' => (string) ($role['label'] ?? Str::headline($roleName)),
                'description' => (string) ($role['description'] ?? 'Role tambahan dengan akses sesuai permission yang diberikan.'),
            ];
        })->values()->all();
    }
}
