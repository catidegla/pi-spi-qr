<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Tests\Laravel;

use Catidegla\PiSpiQr\Exceptions\MalformedPayload;
use Catidegla\PiSpiQr\Laravel\Facades\PiSpi as Facade;
use Catidegla\PiSpiQr\Laravel\PiSpi;
use Catidegla\PiSpiQr\Laravel\PiSpiServiceProvider;
use Catidegla\PiSpiQr\MerchantChannel;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;

final class BridgeTest extends TestCase
{
    #[Test]
    public function the_builder_resolves_from_the_container_and_the_facade(): void
    {
        $this->assertInstanceOf(PiSpi::class, $this->app->make(PiSpi::class));
        $this->assertInstanceOf(PiSpi::class, $this->app->make('pi-spi'));
        $this->assertSame($this->app->make(PiSpi::class), $this->app->make('pi-spi'));
        $this->assertSame(
            (string) $this->app->make(PiSpi::class)->static(),
            (string) Facade::static(),
        );
    }

    #[Test]
    public function config_fills_in_everything_the_call_site_does_not_repeat(): void
    {
        $payload = Facade::dynamic(1500, 'TILL-1-000842')->toPayloadObject();

        $this->assertSame([], $payload->problems(), implode(' | ', $payload->problems()));
        $this->assertSame(self::ALIAS, $payload->alias());
        $this->assertSame('CI', $payload->country());
        $this->assertSame('Chez Fatou', $payload->merchantName());
        $this->assertSame('Abidjan', $payload->merchantCity());
        $this->assertSame(1500, $payload->amount());
        $this->assertSame('TILL-1-000842', $payload->transactionId());
        $this->assertSame(MerchantChannel::DYNAMIC, $payload->channel());
    }

    #[Test]
    public function every_builder_the_core_offers_is_reachable_through_config(): void
    {
        $cases = [
            'static' => Facade::static(),
            'static with amount' => Facade::staticWithAmount(2000),
            'dynamic' => Facade::dynamic(2500, 'T-1'),
            'transfer' => Facade::transfer(),
        ];

        foreach ($cases as $label => $qr) {
            $payload = $qr->toPayloadObject();
            $this->assertSame([], $payload->problems(), $label.': '.implode(' | ', $payload->problems()));
        }
    }

    #[Test]
    public function an_argument_beats_the_configured_value(): void
    {
        $payload = Facade::static('7a0f1d9e-2b64-4c31-9d55-8ee2f4b17c03', 'SN')->toPayloadObject();

        $this->assertSame('7a0f1d9e-2b64-4c31-9d55-8ee2f4b17c03', $payload->alias());
        $this->assertSame('SN', $payload->country());

        // The merchant identity still comes from config, because overriding the
        // beneficiary of one QR is routine and changing who the merchant is is not.
        $this->assertSame('Chez Fatou', $payload->merchantName());
    }

    #[Test]
    public function a_missing_alias_says_what_to_set_rather_than_encoding_nothing(): void
    {
        config()->set('pi-spi.alias', null);

        $this->expectException(MalformedPayload::class);
        $this->expectExceptionMessageMatches('/PI_SPI_ALIAS/');

        $this->app->make(PiSpi::class)->static();
    }

    #[Test]
    public function a_missing_country_is_refused_rather_than_guessed(): void
    {
        config()->set('pi-spi.country', null);

        $this->expectException(MalformedPayload::class);
        $this->expectExceptionMessageMatches('/PI_SPI_COUNTRY/');

        $this->app->make(PiSpi::class)->static();
    }

    #[Test]
    public function the_published_config_is_the_one_the_provider_merges(): void
    {
        // Publishing a stale copy of the defaults is the classic way a package
        // config drifts, so assert the shipped file is the file on offer.
        $shipped = realpath(__DIR__.'/../../config/pi-spi.php');
        $offered = ServiceProvider::pathsToPublish(PiSpiServiceProvider::class, 'pi-spi-config');

        $this->assertIsString($shipped);
        $this->assertContains($shipped, array_map('realpath', array_keys($offered)));
        $this->assertSame([config_path('pi-spi.php')], array_values($offered));
    }

    #[Test]
    public function reading_a_scanned_payload_needs_no_config_at_all(): void
    {
        $scanned = (string) Facade::dynamic(750, 'T-2');

        $this->assertTrue(Facade::isValid($scanned));
        $this->assertSame([], Facade::problems($scanned));
        $this->assertSame(750, Facade::parse($scanned)->amount());
    }

    #[Test]
    public function a_payload_from_another_scheme_is_false_rather_than_an_exception(): void
    {
        // Brazil's Pix, which is the other EMV scheme people actually scan.
        $pix = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

        $this->assertFalse(Facade::isValid($pix));
        $this->assertNotSame([], Facade::problems($pix));

        // And a string that is not EMV at all comes back as a problem, not a throw.
        $this->assertFalse(Facade::isValid('not a qr'));
        $this->assertNotSame([], Facade::problems('not a qr'));
    }
}
