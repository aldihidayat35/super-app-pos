<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Get company name from settings.
     */
    public static function getCompanyName(): string
    {
        $fallback = (string) config('app.name', 'GudangToko');

        try {
            if (! Schema::hasTable('system_settings')) {
                return $fallback;
            }

            return (string) (static::where('key', 'company_name')
                ->where('group', 'general')
                ->value('value') ?? $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * Get company logo path from settings.
     */
    public static function getCompanyLogo(): ?string
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return null;
            }

            $path = static::where('key', 'logo_path')
                ->where('group', 'general')
                ->value('value');
        } catch (Throwable) {
            return null;
        }

        return $path ? asset('storage/'.$path) : null;
    }
}
