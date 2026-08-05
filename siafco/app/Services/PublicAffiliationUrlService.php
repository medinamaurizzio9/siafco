<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RuntimeException;

class PublicAffiliationUrlService
{
    public const ROUTE_NAME = 'public-affiliation.index';

    public function resolve(): string
    {
        $route = Route::getRoutes()->getByName(self::ROUTE_NAME);

        if (! $route) {
            throw new RuntimeException('La ruta publica de afiliacion no esta registrada.');
        }

        $uri = $route->uri();
        if (str_contains($uri, '{')) {
            throw new RuntimeException('La ruta publica de afiliacion no debe requerir parametros.');
        }

        return $this->validate($this->trustedBaseUrl().'/'.ltrim($uri, '/'));
    }

    private function trustedBaseUrl(): string
    {
        $configured = (string) config('app.url', 'http://localhost');
        $parts = parse_url($configured);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return 'http://localhost';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('APP_URL no debe contener credenciales.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('APP_URL debe usar http o https.');
        }

        $authority = $scheme.'://'.$parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':'.$parts['port'];
        }

        return rtrim($authority, '/');
    }

    private function validate(string $url): string
    {
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || empty($parts['scheme'])
            || empty($parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException('La URL publica de afiliacion no es valida.');
        }

        return $url;
    }
}
