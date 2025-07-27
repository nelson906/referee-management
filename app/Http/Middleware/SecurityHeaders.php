<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and add security headers
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Apply security headers based on environment and request type
        $this->addBasicSecurityHeaders($response);
        $this->addContentSecurityPolicy($response, $request);
        $this->addHstsHeader($response, $request);
        $this->addAdditionalSecurityHeaders($response);

        return $response;
    }

    /**
     * Add basic security headers
     */
    private function addBasicSecurityHeaders(Response $response): void
    {
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking protection
        $response->headers->set('X-Frame-Options', 'DENY');

        // XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy (formerly Feature Policy)
        $response->headers->set('Permissions-Policy', $this->getPermissionsPolicy());

        // Remove server information
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');
    }

    /**
     * Add Content Security Policy based on environment
     */
    private function addContentSecurityPolicy(Response $response, Request $request): void
    {
        // Skip CSP for API requests to avoid breaking integrations
        if ($request->is('api/*')) {
            return;
        }

        $csp = $this->buildContentSecurityPolicy($request);

        if (config('app.env') === 'production') {
            $response->headers->set('Content-Security-Policy', $csp);
        } else {
            // Use report-only in development for easier debugging
            $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        }
    }

    /**
     * Build Content Security Policy string
     */
    private function buildContentSecurityPolicy(Request $request): string
    {
        $baseUrl = config('app.url');
        $isAdmin = $request->is('admin/*') || $request->is('super-admin/*');

        $policies = [
            // Default source
            "default-src 'self'",

            // Scripts - more permissive for admin areas due to charts and interactive components
            $isAdmin
                ? "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com"
                : "script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net",

            // Styles - allow inline for Tailwind and admin components
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",

            // Fonts
            "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com",

            // Images - allow data URLs for charts and base64 images
            "img-src 'self' data: https: blob:",

            // Media
            "media-src 'self'",

            // Objects
            "object-src 'none'",

            // Base URI
            "base-uri 'self'",

            // Form actions
            "form-action 'self'",

            // Frame ancestors (already covered by X-Frame-Options but good to have)
            "frame-ancestors 'none'",

            // Connect sources - for AJAX calls and monitoring
            $this->getConnectSrc($request),

            // Worker sources
            "worker-src 'self' blob:",

            // Manifest
            "manifest-src 'self'",
        ];

        // Add report URI in production
        if (config('app.env') === 'production' && config('golf.security.csp_report_uri')) {
            $policies[] = "report-uri " . config('golf.security.csp_report_uri');
        }

        return implode('; ', $policies);
    }

    /**
     * Get connect-src policy based on request context
     */
    private function getConnectSrc(Request $request): string
    {
        $sources = ["'self'"];

        // Add monitoring endpoints for admin areas
        if ($request->is('admin/*') || $request->is('super-admin/*')) {
            $sources[] = 'https://api.github.com'; // For version checks if needed
        }

        // Add external APIs if configured
        if (config('golf.integrations.external_apis')) {
            foreach (config('golf.integrations.external_apis') as $api) {
                $sources[] = $api;
            }
        }

        return 'connect-src ' . implode(' ', $sources);
    }

    /**
     * Add HSTS header for HTTPS requests
     */
    private function addHstsHeader(Response $response, Request $request): void
    {
        if ($request->isSecure() && config('app.env') === 'production') {
            // 1 year HSTS with includeSubDomains and preload
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }
    }

    /**
     * Add additional security headers
     */
    private function addAdditionalSecurityHeaders(Response $response): void
    {
        // Expect-CT header for Certificate Transparency (if configured)
        if (config('golf.security.expect_ct_url')) {
            $response->headers->set(
                'Expect-CT',
                'max-age=86400, enforce, report-uri="' . config('golf.security.expect_ct_url') . '"'
            );
        }

        // Cross-Origin Embedder Policy
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');

        // Cross-Origin Opener Policy
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Cross-Origin Resource Policy
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // Cache control for sensitive pages
        if ($this->isSensitivePage($response)) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }

    /**
     * Get Permissions Policy string
     */
    private function getPermissionsPolicy(): string
    {
        $policies = [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'encrypted-media=()',
            'fullscreen=(self)',
            'picture-in-picture=()',
        ];

        return implode(', ', $policies);
    }

    /**
     * Check if the current page contains sensitive information
     */
    private function isSensitivePage(Response $response): bool
    {
        $request = request();

        // Admin pages are always considered sensitive
        if ($request->is('admin/*') || $request->is('super-admin/*')) {
            return true;
        }

        // Profile and settings pages
        if ($request->is('profile*') || $request->is('settings*')) {
            return true;
        }

        // API endpoints with sensitive data
        if ($request->is('api/statistics/*') || $request->is('api/monitoring/*')) {
            return true;
        }

        // Check response content for sensitive keywords
        $content = $response->getContent();
        if ($content && is_string($content)) {
            $sensitiveKeywords = [
                'password',
                'token',
                'secret',
                'private',
                'confidential',
                'api_key',
            ];

            foreach ($sensitiveKeywords as $keyword) {
                if (stripos($content, $keyword) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
