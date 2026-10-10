<?php

declare(strict_types=1);

return [
    /*
     * Proxies in front of the app (nginx, Caddy, Cloudflare), read by Laravel's TrustProxies middleware.
     * Without them the login rate limit counts every request as the proxy's IP and URLs come out as http.
     * Comma-separated IPs or CIDRs, or "*" when only the proxy can reach the app.
     */
    'proxies' => env('TRUSTED_PROXIES'),
];
