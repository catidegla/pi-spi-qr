<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Tests\Laravel;

use Catidegla\PiSpiQr\Crc16;
use Catidegla\PiSpiQr\Laravel\Facades\PiSpi;
use Catidegla\PiSpiQr\Laravel\Rules\PiSpiPayload;
use Catidegla\PiSpiQr\Spec;
use Catidegla\PiSpiQr\Tlv;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

final class RuleTest extends TestCase
{
    #[Test]
    public function a_conforming_payload_passes(): void
    {
        $validator = $this->validate((string) PiSpi::dynamic(1500, 'TILL-1-000842'));

        $this->assertTrue($validator->passes(), implode(' | ', $validator->errors()->all()));
    }

    #[Test]
    public function a_real_qr_from_another_scheme_fails_on_the_scheme_identifier(): void
    {
        // A Brazilian Pix code, which is the other EMV QR people actually
        // scan. It keeps its account template in 26 rather than 36, so what
        // fails first is that there is no PI-SPI identifier at all.
        $pix = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

        $errors = $this->validate($pix)->errors()->get('qr');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not a PI-SPI QR', implode(' ', $errors));
    }

    #[Test]
    public function a_payload_altered_after_printing_fails_on_the_checksum(): void
    {
        $good = (string) PiSpi::staticWithAmount(1500);

        // Move the amount without recomputing the sum, which is what a tampered
        // sticker over a printed QR amounts to.
        $tampered = str_replace('54041500', '54049500', $good);
        $this->assertNotSame($good, $tampered);

        $errors = $this->validate($tampered)->errors()->get('qr');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('checksum does not match', implode(' ', $errors));
    }

    #[Test]
    public function every_broken_rule_is_reported_not_just_the_first(): void
    {
        // Wrong scheme and a short alias: two faults, one payload, and it
        // decodes cleanly, so nothing here short-circuits on a parse failure.
        $body = Tlv::encode([
            Spec::ID_PAYLOAD_FORMAT => Spec::PAYLOAD_FORMAT_INDICATOR,
            Spec::ID_MERCHANT_ACCOUNT => Tlv::encode([
                Spec::SUB_GUI => 'br.gov.bcb.pix',
                Spec::SUB_ALIAS => 'short-alias',
            ]),
            Spec::ID_MERCHANT_CATEGORY => '0000',
            Spec::ID_CURRENCY => Spec::CURRENCY,
            Spec::ID_COUNTRY => 'CI',
            Spec::ID_MERCHANT_NAME => 'X',
            Spec::ID_MERCHANT_CITY => 'X',
            Spec::ID_ADDITIONAL_DATA => Tlv::encode([Spec::SUB_MERCHANT_CHANNEL => '000']),
        ]).Spec::ID_CRC.'04';

        $errors = $this->validate($body.Crc16::of($body))->errors()->get('qr');

        $this->assertGreaterThan(1, count($errors), implode(' | ', $errors));
        $this->assertStringContainsString('another scheme', implode(' ', $errors));
        $this->assertStringContainsString('36 character payment address', implode(' ', $errors));
    }

    #[Test]
    public function an_unreadable_string_fails_without_throwing(): void
    {
        $errors = $this->validate('not a qr at all')->errors()->get('qr');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not a readable QR payload', implode(' ', $errors));
    }

    #[Test]
    public function a_value_that_is_not_a_string_fails_rather_than_raising_a_type_error(): void
    {
        $this->assertNotEmpty($this->validate(1500)->errors()->get('qr'));
        $this->assertNotEmpty($this->validate(['a', 'b'])->errors()->get('qr'));
    }

    #[Test]
    public function an_absent_value_is_left_to_required_like_every_other_rule(): void
    {
        // Not implicit, on purpose: a rule that fires on a missing field makes
        // an optional QR impossible to express. Pair it with required, which
        // is what the documented usage does.
        $this->assertTrue($this->validate('')->passes());

        $withRequired = Validator::make(['qr' => ''], ['qr' => ['required', new PiSpiPayload]]);

        $this->assertFalse($withRequired->passes());
    }

    #[Test]
    public function requiring_an_amount_rejects_a_qr_the_payer_would_fill_in(): void
    {
        $open = (string) PiSpi::static();

        $this->assertTrue($this->validate($open)->passes());

        $errors = $this->validate($open, requireAmount: true)->errors()->get('qr');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('carries no amount', implode(' ', $errors));
    }

    #[Test]
    public function requiring_an_amount_still_accepts_one_that_carries_it(): void
    {
        $validator = $this->validate((string) PiSpi::staticWithAmount(1500), requireAmount: true);

        $this->assertTrue($validator->passes(), implode(' | ', $validator->errors()->all()));
    }

    #[Test]
    public function the_attribute_name_reaches_the_message(): void
    {
        $errors = $this->validate('not a qr at all')->errors()->get('qr');

        $this->assertStringContainsString('qr', implode(' ', $errors));
        $this->assertStringNotContainsString(':attribute', implode(' ', $errors));
    }

    private function validate(mixed $value, bool $requireAmount = false): \Illuminate\Validation\Validator
    {
        return Validator::make(['qr' => $value], ['qr' => [new PiSpiPayload($requireAmount)]]);
    }
}
