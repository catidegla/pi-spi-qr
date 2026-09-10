<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Tests\Laravel;

use Catidegla\PiSpiQr\Laravel\Facades\PiSpi;
use Catidegla\PiSpiQr\Laravel\PiSpiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const ALIAS = '341c3e1b-4312-49ec-b75e-4c8c74c10fd7';

    protected function getPackageProviders($app): array
    {
        return [PiSpiServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['PiSpi' => PiSpi::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('pi-spi.alias', self::ALIAS);
        $app['config']->set('pi-spi.country', 'CI');
        $app['config']->set('pi-spi.merchant.name', 'Chez Fatou');
        $app['config']->set('pi-spi.merchant.city', 'Abidjan');
    }
}
