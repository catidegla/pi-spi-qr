<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Tests;

use Catidegla\PiSpiQr\Crc16;
use Catidegla\PiSpiQr\MerchantChannel;
use Catidegla\PiSpiQr\Payload;
use Catidegla\PiSpiQr\Spec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The worked example BCEAO publishes, treated as the conformance test.
 *
 * Everything else in this package is an opinion about what the specification
 * means. This file is the one place where the specification answers back, so
 * if it fails, the package is wrong and not the vector.
 */
final class OfficialVectorTest extends TestCase
{
    /** From the "Spécifications techniques du QR Code PI-SPI" page, CRC field excluded. */
    private const BODY = '00020136560012int.bceao.pi0136111c3e1b-4312-49ec-b75e-4c8c74c10fd7520400005303952540410005802CI5901X6001X62270516F2YG7643D5HH202311035006304';

    /** The checksum that page says the body produces. */
    private const EXPECTED_CRC = '9618';

    #[Test]
    public function the_checksum_matches_the_published_worked_example(): void
    {
        $this->assertSame(self::EXPECTED_CRC, Crc16::of(self::BODY));
    }

    #[Test]
    public function the_complete_vector_verifies_against_itself(): void
    {
        $this->assertTrue(Crc16::verify(self::BODY.self::EXPECTED_CRC));
    }

    #[Test]
    public function one_altered_character_breaks_the_checksum(): void
    {
        // The point of the CRC per the specification: detecting that a printed
        // QR has been tampered with. Change the amount from 1000 to 9000.
        $tampered = str_replace('5404100058', '5404900058', self::BODY);

        $this->assertNotSame(self::BODY, $tampered, 'the tamper did not apply, so this test proves nothing');
        $this->assertFalse(Crc16::verify($tampered.self::EXPECTED_CRC));
    }

    #[Test]
    public function the_vector_decodes_into_the_fields_the_specification_describes(): void
    {
        $payload = Payload::parse(self::BODY.self::EXPECTED_CRC);

        $this->assertSame(Spec::GUI, $payload->gui());
        $this->assertSame('111c3e1b-4312-49ec-b75e-4c8c74c10fd7', $payload->alias());
        $this->assertSame(Spec::ALIAS_LENGTH, strlen((string) $payload->alias()));
        $this->assertSame('0000', $payload->merchantCategory());
        $this->assertSame(Spec::CURRENCY, $payload->currency());
        $this->assertSame(1000, $payload->amount());
        $this->assertSame('CI', $payload->country());
        $this->assertSame('X', $payload->merchantName());
        $this->assertSame('X', $payload->merchantCity());
        $this->assertSame('F2YG7643D5HH2023', $payload->transactionId());
        $this->assertSame('500', $payload->channel());
        $this->assertSame(self::EXPECTED_CRC, $payload->checksum());
    }

    #[Test]
    public function the_official_vector_passes_our_own_validation(): void
    {
        // If our rules reject BCEAO's own example, our rules are wrong. This is
        // also what keeps MerchantChannel permissive: the vector carries 500,
        // which appears in no published list of channels.
        $payload = Payload::parse(self::BODY.self::EXPECTED_CRC);

        $this->assertSame([], $payload->problems(), implode(' | ', $payload->problems()));
        $this->assertNotContains('500', MerchantChannel::documented());
    }

    #[Test]
    public function a_two_byte_checksum_is_padded_to_four_characters(): void
    {
        // The specification's own illustration: hex 007B is written "6304007B".
        // A naive dechex() would emit "7B" and produce a three character field.
        $this->assertSame(4, strlen(Crc16::of('anything')));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{4}$/', Crc16::of('anything'));
    }
}
