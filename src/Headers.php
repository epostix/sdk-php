<?php

declare(strict_types=1);

namespace Epostix;

use Psr\Http\Message\ResponseInterface;

final class Headers
{
    public static function rateLimit(ResponseInterface $response): ?RateLimitSnapshot
    {
        $limit = self::integer($response, 'RateLimit-Limit');
        $remaining = self::integer($response, 'RateLimit-Remaining');
        $reset = self::integer($response, 'RateLimit-Reset');
        $policy = $response->getHeaderLine('RateLimit-Policy');
        $scope = $response->getHeaderLine('RateLimit-Scope');

        if ($limit === null && $remaining === null && $reset === null && $policy === '' && $scope === '') {
            return null;
        }

        $burst = null;
        $window = null;

        if ($policy !== '' && preg_match('/^\s*(\d+)\s*;\s*w\s*=\s*(\d+)\s*$/', $policy, $matches) === 1) {
            $burst = (int) $matches[1];
            $window = (int) $matches[2];
        }

        return new RateLimitSnapshot(
            $limit,
            $remaining,
            $reset,
            $burst,
            $window,
            $scope === '' ? null : $scope,
            $policy === '' ? null : $policy,
        );
    }

    public static function retryAfterSeconds(ResponseInterface $response): ?int
    {
        return self::integer($response, 'Retry-After');
    }

    private static function integer(ResponseInterface $response, string $name): ?int
    {
        $raw = $response->getHeaderLine($name);

        if ($raw === '' || preg_match('/^-?\d+$/', trim($raw)) !== 1) {
            return null;
        }

        return (int) trim($raw);
    }
}
