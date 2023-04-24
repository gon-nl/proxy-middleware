<?php

namespace GonNl\ProxyMiddleware;

use Illuminate\Support\ServiceProvider;

class ProxyMiddlewareServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/proxy-middleware.php', 'proxy-middleware');

        $this->app->bind('App\Http\Middleware\TrustProxies', 'GonNl\ProxyMiddleware\Http\Middleware\TrustProxies');
    }
}
