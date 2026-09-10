<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Laravel\Rules;

use Catidegla\PiSpiQr\Exceptions\MalformedPayload;
use Catidegla\PiSpiQr\Payload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a scanned QR payload arriving from a client.
 *
 * The reason this belongs in a form request rather than deeper in the
 * application: a scanned payload is user input from an untrusted camera. It
 * may be smudged, truncated, from another payment scheme entirely, or altered
 * after printing. Treating it as a string until somebody tries to pay against
 * it pushes all of those into a failure much further from the person who can
 * do anything about it.
 *
 *     $request->validate([
 *         'qr' => ['required', 'string', new PiSpiPayload],
 *     ]);
 *
 * Every broken rule is reported, not just the first, because a payload that
 * fails on the scheme identifier usually fails on the checksum too and the
 * operator wants both at once.
 */
class PiSpiPayload implements ValidationRule
{
    /**
     * @param bool $requireAmount reject a QR whose amount the payer must enter,
     *                            for flows that can only accept a fixed sum
     */
    public function __construct(private readonly bool $requireAmount = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a scanned PI-SPI QR payload.');

            return;
        }

        try {
            $payload = Payload::parse($value);
        } catch (MalformedPayload $e) {
            $fail(sprintf('The :attribute is not a readable QR payload: %s', $e->getMessage()));

            return;
        }

        foreach ($payload->problems() as $problem) {
            $fail(sprintf('The :attribute %s', $problem));
        }

        if ($this->requireAmount && ! $payload->hasAmount()) {
            $fail('The :attribute carries no amount, and this payment cannot be for an amount the payer chooses.');
        }
    }
}
