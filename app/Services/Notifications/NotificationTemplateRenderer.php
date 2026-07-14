<?php

namespace App\Services\Notifications;

use App\Exceptions\ServiceException;
use App\Models\NotificationTemplate;

class NotificationTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{subject: string|null, body: string}
     */
    public function render(NotificationTemplate $template, array $context): array
    {
        $this->assertAllowedPlaceholders($template, $context);

        return [
            'subject' => $template->subject ? $this->replace($template->subject, $context) : null,
            'body' => $this->replace($template->body, $context),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function preview(string $content, array $context): string
    {
        return $this->replace($content, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function replace(string $content, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{{ '.$key.' }}'] = (string) $value;
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        return strtr($content, $replacements);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertAllowedPlaceholders(NotificationTemplate $template, array $context): void
    {
        $configured = config('notifications.template_variables.'.($template->key ?: 'daily_report'), []);
        $allowed = array_values(array_unique(array_merge(
            $this->normalizeVariables($configured),
            $this->normalizeVariables($template->allowed_variables)
        )));

        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $template->body."\n".($template->subject ?? ''), $matches);
        $placeholders = $matches[1];
        $invalid = array_values(array_diff($placeholders, $allowed));
        if ($invalid !== []) {
            throw new ServiceException('Template memakai variable yang tidak diizinkan: '.implode(', ', $invalid));
        }

        $missing = array_values(array_diff($placeholders, array_keys($context)));
        if ($missing !== []) {
            throw new ServiceException('Data template belum lengkap: '.implode(', ', $missing));
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeVariables(mixed $variables): array
    {
        if (! is_array($variables)) {
            return [];
        }

        $normalized = [];
        foreach ($variables as $variable) {
            if (is_string($variable) && $variable !== '') {
                $normalized[] = $variable;
            }
        }

        return $normalized;
    }
}
