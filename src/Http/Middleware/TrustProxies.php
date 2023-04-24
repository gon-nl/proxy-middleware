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
     * List of headers that are used to check the presence of a proxy.
     */
    private $platformHeaders = [
        'Do-Connecting-Ip',
        'HTTP_CF_CONNECTING_IP',
    ];

    /**
     * This method is responsible for setting the trusted proxy IP addresses on the request object based on the headers.
     * If the headers don't contain the IP address, it sets the trusted proxy to either Cloudflare or all proxies based on the configuration.
     * 
     * @param Request $request The request object to set the trusted proxy on.
     * @return void
     */
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        // If the IP address is not found in the headers.
        if (!$this->findAndSetIpBasedOnHeader($request)) {
            // Check if the configuration allows for trusting all proxies.
            $trustAllProxies = Config::get('proxy-middleware.trust-all');
            
            // If trust all proxies is true, set trusted proxy to all.
            if ($trustAllProxies) {
                $this->setTrustedProxyAll();
            } 
            // Otherwise, set trusted proxy to Cloudflare.
            else {
                $this->setTrustedProxyCloudflare($request);
            }
        }
    }

    /**
     * Find and set the IP address based on the platform headers.
     * 
     * @param Request $request The request object to extract the IP address from.
     * @return string|null The IP address that was found or null if not found.
     */
    private function findAndSetIpBasedOnHeader(Request $request): ?string
    {
        $ip = false;

        $cachedHeader = Cache::get('header', false);

        if ($cachedHeader) {
            $ip = $this->getIpFromHeader($request, $cachedHeader);
            $request->server->add(['REMOTE_ADDR' => $ip]);
            return $ip;
        }

        // Check each platform header in turn to find the IP address.
        foreach ($this->platformHeaders as $header) {
            $ip = $this->getIpFromHeader($request, $header);

            if ($ip) {
                // If an IP address is found, break out of the loop.
                Cache::put('header', $header, now()->addDays(7));
                break;
            }
        }

        // Add the REMOTE_ADDR header to the server array.
        $request->server->add(['REMOTE_ADDR' => $ip]);

        // Return the IP address that was found, or null if not found.
        return $ip;
    }

    /**
     * Get the IP address from a header, falling back to the server variable if not found.
     * 
     * @param Request $request The request object to extract the IP address from.
     * @param string $header The name of the header to check for the IP address.
     * @return string|null The IP address that was found or null if not found.
     */
    private function getIpFromHeader(Request $request, string $header): ?string
    {
        // Get the IP address from the header if present, otherwise from the server variable.
        return $request->header($header) ?? $request->server->get($header);
    }

    /**
     * Sets the trusted proxies on the request to the value of Cloudflare ips.
     */
    private function setTrustedProxyCloudflare(Request $request): void
    {
        $cachedProxies = Cache::rememberForever('cf_ips', fn () => $this->getCloudFlareProxies());

        if (\is_array($cachedProxies) && \count($cachedProxies) > 0) {
            $this->proxies = array_merge($this->proxies, $cachedProxies);
        }

        $request->setTrustedProxies($this->proxies);
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
