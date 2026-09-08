<?php

namespace App\Http\Middleware;

use App\Models\Conference;
use App\Models\WebsitePageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackWebsitePageview
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     * Zero-latency execution: runs asynchronously after response transmission.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->recordPageview($request, $response);
        } catch (Throwable) {
            // Silently ignore to guarantee core application stability
        }
    }

    protected function recordPageview(Request $request, Response $response): void
    {
        $path = trim($request->path(), '/');

        // 1. Ignore static assets, health checks, webhooks, and debugging routes
        if ($this->shouldIgnore($request, $path)) {
            return;
        }

        $userAgent = (string) $request->userAgent();

        // 2. Ignore automated bots, search crawlers, and CLI tools
        if ($this->isBot($userAgent)) {
            return;
        }

        // 3. Resolve Conference association
        $conferenceId = $this->resolveConferenceId($request, $path);

        // 4. Sanitize and mask sensitive paths (e.g. portal tokens)
        $maskedPath = $this->maskSensitivePath($path);
        $pageType = $this->determinePageType($request, $maskedPath);

        // 5. Anonymize visitor identification (Salted daily hash - GDPR compliant, no raw IP)
        $dateSalt = now()->format('Y-m-d');
        $visitorHash = hash('sha256', ($request->ip() ?? '127.0.0.1') . $userAgent . $dateSalt . config('app.key'));

        // 6. Device, Browser & Platform Classification
        $deviceType = $this->detectDevice($userAgent);
        $browser = $this->detectBrowser($userAgent);
        $platform = $this->detectPlatform($userAgent);

        // 7. Referrer & Traffic Channel Classification
        $referrerRaw = (string) $request->headers->get('referer');
        [$cleanReferrer, $referrerType] = $this->classifyReferrer($referrerRaw, $request->getHost());

        WebsitePageView::create([
            'conference_id' => $conferenceId,
            'visitor_hash' => $visitorHash,
            'path' => '/' . $maskedPath,
            'page_type' => $pageType,
            'method' => strtoupper($request->method()),
            'status_code' => $response->getStatusCode(),
            'referrer' => $cleanReferrer,
            'referrer_type' => $referrerType,
            'device_type' => $deviceType,
            'browser' => $browser,
            'platform' => $platform,
            'visited_at' => now(),
        ]);
    }

    protected function shouldIgnore(Request $request, string $path): bool
    {
        if ($path === 'up' || $path === 'favicon.ico' || $path === 'robots.txt') {
            return true;
        }

        if (str_starts_with($path, 'build/') ||
            str_starts_with($path, 'api/webhooks') ||
            str_starts_with($path, '_debugbar') ||
            str_starts_with($path, 'livewire/')) {
            return true;
        }

        // Check common file extensions
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ignoredExtensions = [
            'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
            'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'zip', 'docx', 'pdf'
        ];

        return in_array($extension, $ignoredExtensions, true);
    }

    protected function isBot(string $userAgent): bool
    {
        if (empty($userAgent)) {
            return true;
        }

        $pattern = '/(googlebot|bingbot|yandex|baiduspider|duckduckbot|slurp|twitterbot|facebookexternalhit|linkedinbot|whatsapp|telegrambot|curl|wget|postman|python-requests|headlesschrome)/i';

        return (bool) preg_match($pattern, $userAgent);
    }

    protected function resolveConferenceId(Request $request, string $path): ?string
    {
        // Check route parameter
        $routeConf = $request->route('conference');
        if ($routeConf instanceof Conference) {
            return $routeConf->id;
        }
        if (is_string($routeConf)) {
            $conf = Conference::where('slug', $routeConf)->orWhere('id', $routeConf)->first();
            if ($conf) return $conf->id;
        }

        // Check first segment of path (e.g. /icoseit or /icoseit/submit)
        $segments = explode('/', $path);
        $firstSegment = $segments[0] ?? '';
        if ($firstSegment && !in_array($firstSegment, ['admin', 'dashboard', 'papers', 'conferences', 'login', 'submission', 'user-manual', 'profile', 'monitoring', 'emails', 'editor-performance'], true)) {
            $conf = Conference::where('slug', $firstSegment)->first();
            if ($conf) return $conf->id;
        }

        // Check active conference in session
        return session('active_conference_id');
    }

    protected function maskSensitivePath(string $path): string
    {
        // Mask token in author portal: submission/access/{token} -> submission/access/:token
        return (string) preg_replace('#^submission/access/[^/]+#', 'submission/access/:token', $path);
    }

    protected function determinePageType(Request $request, string $maskedPath): string
    {
        if (str_ends_with($maskedPath, '/submit') || $maskedPath === 'submit') {
            return 'submit';
        }

        if (str_starts_with($maskedPath, 'submission/access/:token')) {
            return 'portal';
        }

        if (str_starts_with($maskedPath, 'user-manual')) {
            return 'manual';
        }

        if (in_array($maskedPath, ['login', 'register', 'change-password', 'forgot-password', 'reset-password'], true)) {
            return 'auth';
        }

        if (str_starts_with($maskedPath, 'admin') ||
            str_starts_with($maskedPath, 'dashboard') ||
            str_starts_with($maskedPath, 'papers') ||
            str_starts_with($maskedPath, 'conferences') ||
            str_starts_with($maskedPath, 'editor-performance') ||
            str_starts_with($maskedPath, 'emails') ||
            str_starts_with($maskedPath, 'monitoring')) {
            return 'staff';
        }

        // Single slug path without slashes is usually a public landing page
        if (!str_contains($maskedPath, '/') && !empty($maskedPath)) {
            return 'landing';
        }

        return 'other';
    }

    protected function detectDevice(string $ua): string
    {
        if (preg_match('/(ipad|tablet|(android(?!.*mobile)))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/(android|iphone|ipod|blackberry|iemobile|opera mini|mobile)/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected function detectBrowser(string $ua): string
    {
        if (preg_match('/edg/i', $ua)) return 'Edge';
        if (preg_match('/chrome|crios/i', $ua) && !preg_match('/opr|opera|edg/i', $ua)) return 'Chrome';
        if (preg_match('/safari/i', $ua) && !preg_match('/chrome|crios|opr|opera|edg/i', $ua)) return 'Safari';
        if (preg_match('/firefox|fxios/i', $ua)) return 'Firefox';
        if (preg_match('/opr|opera/i', $ua)) return 'Opera';

        return 'Other';
    }

    protected function detectPlatform(string $ua): string
    {
        if (preg_match('/windows nt/i', $ua)) return 'Windows';
        if (preg_match('/macintosh|mac os x/i', $ua) && !preg_match('/iphone|ipad|ipod/i', $ua)) return 'macOS';
        if (preg_match('/android/i', $ua)) return 'Android';
        if (preg_match('/iphone|ipad|ipod/i', $ua)) return 'iOS';
        if (preg_match('/linux/i', $ua)) return 'Linux';

        return 'Other';
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    protected function classifyReferrer(string $referrer, string $currentHost): array
    {
        if (empty($referrer)) {
            return [null, 'direct'];
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if (!$host || strtolower($host) === strtolower($currentHost)) {
            return [null, 'direct'];
        }

        $hostLower = strtolower($host);

        if (preg_match('/(google|bing|yahoo|duckduckgo|baidu|yandex)\./i', $hostLower)) {
            return [$hostLower, 'search'];
        }

        if (preg_match('/(whatsapp|telegram|facebook|twitter|x\.com|linkedin|instagram|t\.co)\./i', $hostLower)) {
            return [$hostLower, 'social'];
        }

        if (preg_match('/(mail\.google|outlook|yahoo\.mail|webmail)\./i', $hostLower)) {
            return [$hostLower, 'email'];
        }

        return [$hostLower, 'external'];
    }
}
