<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Tests;

use Catidegla\PiSpiQr\Exceptions\MalformedPayload;
use Catidegla\PiSpiQr\Payload;
use Catidegla\PiSpiQr\QrCode;
use Catidegla\PiSpiQr\Tlv;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Reading payloads that somebody else produced, including bad ones.
 *
 * A reader at a till meets malformed input as a matter of course: a smudged
 * print, a QR from another scheme, a truncated scan. None of those should
 * throw something the caller cannot act on.
 */
final class PayloadTest extends TestCase
{
    private const ALIAS = '341c3e1b-4312-49ec-b75e-4c8c74c10fd7';

    #[Test]
    public function a_payload_from_another_emv_scheme_is_reported_not_thrown(): void
    {
        // Brazil's Pix, structurally valid EMV with a different scheme id.
        $body = Tlv::encode([
            '00' => '01',
            '26' => Tlv::encode(['00' => 'br.gov.bcb.pix', '01' => 'chave@example.com']),
            '52' => '0000',
            '53' => '986',
            '58' => 'BR',
            '59' => 'FULANO',
            '60' => 'SAO PAULO',
        ]).'6304';

        $payload = Payload::parse($body.\Catidegla\PiSpiQr\Crc16::of($body));
        $problems = implode(' | ', $payload->problems());

        $this->assertFalse($payload->isValid());
        $this->assertStringContainsString('not a PI-SPI QR', $problems);
        $this->assertNull($payload->alias());
    }

    #[Test]
    public function an_empty_payload_throws(): void
    {
        $this->expectException(MalformedPayload::class);

        Payload::parse('   ');
    }

    #[Test]
    public function a_truncated_field_throws_with_the_offset(): void
    {
        $this->expectException(MalformedPayload::class);
        $this->expectExceptionMessageMatches('/declares \d+ characters but only \d+ remain/');

        // Field 59 claims 20 characters and supplies four.
        Payload::parse('0002015920ABCD');
    }

    #[Test]
    public function a_repeated_field_throws_rather_than_silently_winning(): void
    {
        $this->expectException(MalformedPayload::class);
        $this->expectExceptionMessageMatches('/appears more than once/');

        Payload::parse('0002010002 01');
    }

    #[Test]
    public function a_broken_checksum_is_named_with_both_values(): void
    {
        $good = QrCode::static(self::ALIAS, 'CI')->toPayload();
        $broken = substr($good, 0, -4).'0000';

        $problems = implode(' | ', Payload::parse($broken)->problems());

        $this->assertStringContainsString('checksum does not match', $problems);
        $this->assertStringContainsString('0000', $problems);
    }

    #[Test]
    public function an_alias_of_the_wrong_length_is_reported(): void
    {
        // A phone number alias is valid on the rail but must never appear in an
        // interoperable QR, where only a 36 character payment address is allowed.
        $payload = QrCode::static('+22997000000', 'BJ')->toPayloadObject();

        $this->assertStringContainsString(
            'only a 36 character payment address',
            implode(' | ', $payload->problems()),
        );
    }

    #[Test]
    public function a_dynamic_qr_without_a_transaction_id_is_reported(): void
    {
        $payload = QrCode::withChannel(self::ALIAS, 'CI', \Catidegla\PiSpiQr\MerchantChannel::DYNAMIC)
            ->amount(500)
            ->toPayloadObject();

        $this->assertStringContainsString(
            'must carry a unique transaction id',
            implode(' | ', $payload->problems()),
        );
    }

    #[Test]
    public function problems_are_collected_rather_than_stopping_at_the_first(): void
    {
        // Wrong country and a bad alias at once. A till operator should see both.
        $payload = QrCode::static('short', 'FR')->toPayloadObject();

        $this->assertGreaterThanOrEqual(2, count($payload->problems()));
    }

    #[Test]
    public function the_payload_stringifies_to_exactly_what_was_parsed(): void
    {
        $original = QrCode::dynamic(self::ALIAS, 'CI', 1000, 'T1')->toPayload();

        $this->assertSame($original, (string) Payload::parse($original));
    }
}
