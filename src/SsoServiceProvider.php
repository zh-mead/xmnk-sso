<?php

namespace ZhMead\XmnkSso;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class SsoServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $source = realpath(__DIR__ . '/config/sso.php');
        if ($this->app instanceof LumenApplication) {
            $this->app->configure('sso');
        } else {
            $this->publishes([
                __DIR__ . '/../config/sso.php' => config_path('sso.php'),
            ]);
        }

        $this->mergeConfigFrom($source, 'sso');

        $this->loadRoutesFrom(__DIR__ . '/ssoApi.php');
    }
}