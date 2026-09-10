<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr;

use Catidegla\PiSpiQr\Exceptions\MalformedPayload;
use Stringable;

/**
 * A PI-SPI QR payload, read back from its own string form.
 *
 * Parsing is deliberately separate from validating. A payload can decode
 * perfectly and still not be a PI-SPI payload, because it belongs to another
 * EMV scheme or because a field is missing, and a reader wants to know which
 * of those it is holding. So parse() throws only when the bytes are not TLV,
 * and everything else arrives as a list from problems().
 */
final class Payload implements Stringable
{
    /**
     * @param array<string, string> $fields top level, in payload order
     * @param array<string, string> $merchantAccount sub-fields of 36
     * @param array<string, string> $additionalData sub-fields of 62
     */
    private function __construct(
        public readonly string $raw,
        private readonly array $fields,
        private readonly array $merchantAccount,
        private readonly array $additionalData,
    ) {}

    /** @throws MalformedPayload when the string is not EMV TLV */
    public static function parse(string $payload): self
    {
        $payload = trim($payload);

        if ($payload === '') {
            throw new MalformedPayload('the payload is empty');
        }

        $fields = Tlv::decode($payload);

        // One level down, and only where the specification says there is one.
        $sub = static function (?string $value): array {
            if ($value === null || $value === '') {
                return [];
            }

            try {
                return Tlv::decode($value);
            } catch (MalformedPayload) {
                // A template that will not decode is reported by problems()
                // rather than thrown, since the rest of the payload may still
                // be readable and worth showing to whoever is debugging it.
                return [];
            }
        };

        return new self(
            $payload,
            $fields,
            $sub($fields[Spec::ID_MERCHANT_ACCOUNT] ?? null),
            $sub($fields[Spec::ID_ADDITIONAL_DATA] ?? null),
        );
    }

    /* ---------------------------------------------------------- accessors */

    public function gui(): ?string
    {
        return $this->merchantAccount[Spec::SUB_GUI] ?? null;
    }

    public function alias(): ?string
    {
        return $this->merchantAccount[Spec::SUB_ALIAS] ?? null;
    }

    public function country(): ?string
    {
        return $this->fields[Spec::ID_COUNTRY] ?? null;
    }

    public function currency(): ?string
    {
        return $this->fields[Spec::ID_CURRENCY] ?? null;
    }

    public function merchantName(): ?string
    {
        return $this->fields[Spec::ID_MERCHANT_NAME] ?? null;
    }

    public function merchantCity(): ?string
    {
        return $this->fields[Spec::ID_MERCHANT_CITY] ?? null;
    }

    public function merchantCategory(): ?string
    {
        return $this->fields[Spec::ID_MERCHANT_CATEGORY] ?? null;
    }

    public function channel(): ?string
    {
        return $this->additionalData[Spec::SUB_MERCHANT_CHANNEL] ?? null;
    }

    public function transactionId(): ?string
    {
        return $this->additionalData[Spec::SUB_REFERENCE_LABEL] ?? null;
    }

    /**
     * The amount in whole francs, or null when the payer enters it.
     *
     * Returned as an int because XOF has no minor unit. A float here would be
     * a bug waiting for a fee calculation to find it.
     */
    public function amount(): ?int
    {
        $raw = $this->fields[Spec::ID_AMOUNT] ?? null;

        return ($raw === null || ! ctype_digit($raw)) ? null : (int) $raw;
    }

    public function hasAmount(): bool
    {
        return isset($this->fields[Spec::ID_AMOUNT]);
    }

    public function checksum(): ?string
    {
        return $this->fields[Spec::ID_CRC] ?? null;
    }

    /**
     * The decoded top level fields.
     *
     * A warning about the keys, because PHP will not let this be tidy: an
     * array key that looks like an integer becomes one, so "36" comes back as
     * int 36 while "00" stays a string because of its leading zero. Lookups
     * are unaffected, since the same coercion applies to the subscript, but
     * anything that inspects the keys themselves meets both types. Use
     * fieldIds() when the ids matter as ids.
     *
     * @return array<array-key, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * The field ids present, as two digit strings, in payload order.
     *
     * @return list<string>
     */
    public function fieldIds(): array
    {
        return array_map(
            static fn ($id): string => str_pad((string) $id, 2, '0', STR_PAD_LEFT),
            array_keys($this->fields),
        );
    }

    /* --------------------------------------------------------- validation */

    /**
     * Every PI-SPI rule this payload breaks, in the order worth reading them.
     *
     * The scheme identifier and the checksum come first because the
     * specification says to check exactly those two before processing
     * anything: the first tells you the QR is even meant for this rail, the
     * second that nobody has altered it since it was printed.
     *
     * @return list<string> empty when the payload conforms
     */
    public function problems(): array
    {
        $problems = [];

        $gui = $this->gui();
        if ($gui === null) {
            $problems[] = 'missing the scheme identifier in 36-00, so this is not a PI-SPI QR';
        } elseif ($gui !== Spec::GUI) {
            $problems[] = sprintf('scheme identifier is "%s", expected "%s", so this QR belongs to another scheme', $gui, Spec::GUI);
        }

        if (! Crc16::verify($this->raw)) {
            $problems[] = sprintf(
                'checksum does not match: the payload carries %s, its contents give %s',
                $this->checksum() ?? 'nothing',
                Crc16::of(substr($this->raw, 0, -4)),
            );
        }

        $alias = $this->alias();
        if ($alias === null || $alias === '') {
            $problems[] = 'missing the alias in 36-01';
        } elseif (strlen($alias) !== Spec::ALIAS_LENGTH) {
            $problems[] = sprintf(
                'alias is %d characters, and only a %d character payment address may appear in an interoperable QR',
                strlen($alias), Spec::ALIAS_LENGTH,
            );
        }

        $format = $this->fields[Spec::ID_PAYLOAD_FORMAT] ?? null;
        if ($format !== Spec::PAYLOAD_FORMAT_INDICATOR) {
            $problems[] = sprintf('payload format indicator is "%s", expected "%s"', $format ?? 'missing', Spec::PAYLOAD_FORMAT_INDICATOR);
        }

        $currency = $this->currency();
        if ($currency !== Spec::CURRENCY) {
            $problems[] = sprintf('currency is "%s", and PI-SPI settles only in %s (XOF)', $currency ?? 'missing', Spec::CURRENCY);
        }

        $country = $this->country();
        if ($country === null) {
            $problems[] = 'missing the country code in 58';
        } elseif (! in_array($country, Spec::COUNTRIES, true)) {
            $problems[] = sprintf('country "%s" is not one of the eight union member states (%s)', $country, implode(', ', Spec::COUNTRIES));
        }

        $channel = $this->channel();
        if ($channel === null) {
            $problems[] = 'missing the merchant channel in 62-11, which is mandatory';
        } elseif (! MerchantChannel::isWellFormed($channel)) {
            $problems[] = sprintf('merchant channel "%s" is not three digits', $channel);
        }

        $txId = $this->transactionId();
        if ($txId !== null && strlen($txId) > Spec::MAX_TRANSACTION_ID) {
            $problems[] = sprintf('transaction id is %d characters, above the %d the guide allows', strlen($txId), Spec::MAX_TRANSACTION_ID);
        }

        if ($channel === MerchantChannel::DYNAMIC && ($txId === null || $txId === '')) {
            $problems[] = 'a dynamic QR must carry a unique transaction id in 62-05';
        }

        if ($this->hasAmount() && $this->amount() === null) {
            $problems[] = sprintf('amount "%s" is not a whole number of francs', $this->fields[Spec::ID_AMOUNT]);
        }

        foreach ([Spec::ID_MERCHANT_NAME => 'merchant name in 59', Spec::ID_MERCHANT_CITY => 'merchant city in 60'] as $id => $label) {
            if (! isset($this->fields[$id])) {
                $problems[] = 'missing the '.$label;
            }
        }

        return $problems;
    }

    public function isValid(): bool
    {
        return $this->problems() === [];
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}
