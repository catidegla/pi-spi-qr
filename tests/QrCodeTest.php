<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Tests;

use Catidegla\PiSpiQr\Crc16;
use Catidegla\PiSpiQr\Exceptions\MalformedPayload;
use Catidegla\PiSpiQr\MerchantChannel;
use Catidegla\PiSpiQr\Payload;
use Catidegla\PiSpiQr\QrCode;
use Catidegla\PiSpiQr\Spec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase
{
    private const ALIAS = '341c3e1b-4312-49ec-b75e-4c8c74c10fd7';

    #[Test]
    public function every_builder_produces_a_payload_that_validates(): void
    {
        $cases = [
            'static' => QrCode::static(self::ALIAS, 'BJ'),
            'static with amount' => QrCode::staticWithAmount(self::ALIAS, 'CI', 1500),
            'dynamic' => QrCode::dynamic(self::ALIAS, 'SN', 2500, 'TILL-1-0001'),
            'transfer' => QrCode::transfer(self::ALIAS, 'TG'),
        ];

        foreach ($cases as $label => $qr) {
            $payload = $qr->toPayloadObject();
            $this->assertSame([], $payload->problems(), $label.': '.implode(' | ', $payload->problems()));
            $this->assertTrue(Crc16::verify((string) $payload), $label.' checksum');
        }
    }

    #[Test]
    public function a_built_payload_round_trips_through_the_parser(): void
    {
        $qr = QrCode::dynamic(self::ALIAS, 'CI', 1000, 'F2YG7643D5HH2023')
            ->merchantName('Chez Fatou')
            ->merchantCity('Abidjan');

        $payload = Payload::parse($qr->toPayload());

        $this->assertSame(self::ALIAS, $payload->alias());
        $this->assertSame(1000, $payload->amount());
        $this->assertSame('CI', $payload->country());
        $this->assertSame('Chez Fatou', $payload->merchantName());
        $this->assertSame('Abidjan', $payload->merchantCity());
        $this->assertSame('F2YG7643D5HH2023', $payload->transactionId());
        $this->assertSame(MerchantChannel::DYNAMIC, $payload->channel());
    }

    #[Test]
    public function the_field_order_is_the_one_the_specification_fixes(): void
    {
        $payload = QrCode::dynamic(self::ALIAS, 'CI', 1000, 'T1')->toPayloadObject();

        // fieldIds() rather than array_keys(), because PHP turns "36" into
        // int 36 and leaves "00" a string, which makes the raw keys a mixed
        // bag that says nothing about ordering.
        $this->assertSame(
            ['00', '36', '52', '53', '54', '58', '59', '60', '62', '63'],
            $payload->fieldIds(),
        );
    }

    #[Test]
    public function a_static_qr_carries_no_amount_so_the_payer_enters_it(): void
    {
        $payload = QrCode::static(self::ALIAS, 'BJ')->toPayloadObject();

        $this->assertFalse($payload->hasAmount());
        $this->assertNull($payload->amount());
    }

    #[Test]
    public function the_amount_is_whole_francs_and_not_minor_units(): void
    {
        // XOF has no minor unit. A thousand francs is 1000, and anything that
        // multiplies by a hundred here overcharges by a factor of a hundred.
        $payload = QrCode::staticWithAmount(self::ALIAS, 'CI', 1000)->toPayloadObject();

        $this->assertSame(1000, $payload->amount());
        $this->assertStringContainsString('54041000', (string) $payload);
    }

    #[Test]
    public function a_negative_amount_is_refused(): void
    {
        $this->expectException(MalformedPayload::class);

        QrCode::staticWithAmount(self::ALIAS, 'CI', -1);
    }

    #[Test]
    public function the_builder_is_immutable_so_a_shared_template_cannot_leak(): void
    {
        // A till that keeps one configured builder and calls amount() per sale
        // must not accumulate state between customers.
        $base = QrCode::static(self::ALIAS, 'CI')->merchantName('Chez Fatou');

        $first = $base->amount(1000)->toPayloadObject();
        $second = $base->amount(2000)->toPayloadObject();

        $this->assertSame(1000, $first->amount());
        $this->assertSame(2000, $second->amount());
        $this->assertFalse($base->toPayloadObject()->hasAmount());
    }

    #[Test]
    public function a_country_outside_the_union_is_reported(): void
    {
        $problems = QrCode::static(self::ALIAS, 'FR')->toPayloadObject()->problems();

        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('not one of the eight union member states', implode(' ', $problems));
    }

    #[Test]
    public function a_lowercase_country_is_normalised_rather_than_rejected(): void
    {
        $this->assertSame('CI', QrCode::static(self::ALIAS, 'ci')->toPayloadObject()->country());
    }

    #[Test]
    public function a_transaction_id_beyond_the_documented_limit_is_reported(): void
    {
        $problems = QrCode::dynamic(self::ALIAS, 'CI', 100, str_repeat('A', Spec::MAX_TRANSACTION_ID + 1))
            ->toPayloadObject()
            ->problems();

        $this->assertStringContainsString('above the 25 the guide allows', implode(' ', $problems));
    }

    #[Test]
    public function an_accented_merchant_name_is_refused_rather_than_mislengthed(): void
    {
        // EMV lengths count characters. "Café" is 4 characters and 5 bytes, so
        // emitting a byte length produces a payload no reader can split.
        $this->expectException(MalformedPayload::class);

        QrCode::static(self::ALIAS, 'CI')->merchantName('Café de la Gare')->toPayload();
    }
}
