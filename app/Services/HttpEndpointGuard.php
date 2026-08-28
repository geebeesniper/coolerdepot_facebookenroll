<?php
namespace App\Services;

class HttpEndpointGuard
{
    public static function assertPublicHttps(string $url): string
    {
        $url = trim($url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('API endpoint must be a valid URL.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            throw new \RuntimeException('Custom API endpoints must use HTTPS.');
        }

        if ($host === ''
            || $host === 'localhost'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            throw new \RuntimeException('Private/internal API hosts are not allowed.');
        }

        $ips = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if (!$resolved) {
                throw new \RuntimeException('API host could not be resolved.');
            }
            $ips = $resolved;
        }

        foreach ($ips as $ip) {
            if (!filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                throw new \RuntimeException(
                    'API endpoint resolves to a private/reserved network.'
                );
            }
        }

        return $url;
    }

    public static function optionalWebsite(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new \RuntimeException('Website link must be a valid HTTPS URL.');
        }

        return $url;
    }
}
