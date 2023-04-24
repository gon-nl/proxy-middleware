<?php

namespace GonNl\ProxyMiddleware\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var null|array<int, string>|string
     */
    protected $proxies = [];

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    /**
     * Handle an incoming request.
     * Set the trusted proxies on the request to the value of Cloudflare ips.
     */
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        Config::get('proxy-middleware.trust-all')
            ? $this->setTrustedProxyAll($request)
            : $this->setTrustedProxyCloudflare($request);
    }

    /**
     * Sets the trusted proxies on the request to the value of Cloudflare ips.
     */
    private function setTrustedProxyCloudflare(): void
    {
        $cachedProxies = Cache::rememberForever('cf_ips', fn () => $this->getCloudFlareProxies());

        if (\is_array($cachedProxies) && \count($cachedProxies) > 0) {
            $this->proxies = array_merge($this->proxies, $cachedProxies);
        }
    }

    /**
     * Sets the trusted proxies on the request to the value of all ips.
     */
    private function setTrustedProxyAll(): void
    {
        $this->proxies = '*';
    }

    /**
     * Get the Cloudflare IP addresses.
     *
     * @return array<int, string>
     */
    private function getCloudFlareProxies(): array
    {
        return array_merge(
            $this->retrieve('https://www.cloudflare.com/ips-v4'),
            $this->retrieve('https://www.cloudflare.com/ips-v6')
        );
    }

    /**
     * Retrieve the IP addresses from the given URL.
     *
     * @return array<int, string>
     */
    private function retrieve(string $url): array
    {
        $response = Http::get($url)->throw();

        return array_filter(explode("\n", $response->body()));
    }
}
