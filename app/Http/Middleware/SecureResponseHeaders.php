<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureResponseHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headers() as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $this->setContentSecurityPolicy($response);

        return $response;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
    }

    private function setContentSecurityPolicy(Response $response): void
    {
        if (! config('security.headers.csp.enabled')) {
            return;
        }

        $header = config('security.headers.csp.report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        if ($response->headers->has($header)) {
            return;
        }

        $directives = config('security.headers.csp.directives', []);

        if (! is_array($directives) || $directives === []) {
            return;
        }

        $response->headers->set($header, implode('; ', $directives));
    }
}
