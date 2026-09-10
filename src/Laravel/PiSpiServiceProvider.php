<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Laravel;

use Catidegla\PiSpiQr\Laravel\Console\QrCommand;
use Illuminate\Support\ServiceProvider;

class PiSpiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/pi-spi.php', 'pi-spi');

        $this->app->singleton(PiSpi::class, fn ($app) => new PiSpi((array) $app['config']->get('pi-spi', [])));
        $this->app->alias(PiSpi::class, 'pi-spi');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([QrCommand::class]);

        $this->publishes([
            __DIR__.'/../../config/pi-spi.php' => config_path('pi-spi.php'),
        ], 'pi-spi-config');
    }
}
