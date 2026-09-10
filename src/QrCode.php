<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr;

use Catidegla\PiSpiQr\Exceptions\MalformedPayload;

/**
 * Builds the four kinds of PI-SPI QR the specification describes.
 *
 * Named constructors rather than one builder with a channel argument, because
 * the four cases differ in what they require: a dynamic QR is meaningless
 * without a transaction id, a transfer QR is presented by the payer so the
 * amount is theirs to enter. Encoding that in the signatures means the wrong
 * combination does not compile rather than failing at a till.
 *
 * Field order is fixed by the specification and is not the caller's business,
 * so it lives here and nowhere else.
 */
final class QrCode
{
    private string $merchantName = 'X';
    private string $merchantCity = 'X';
    private string $merchantCategory = Spec::DEFAULT_MERCHANT_CATEGORY;
    private ?string $transactionId = null;
    private ?int $amount = null;

    private function __construct(
        private readonly string $alias,
        private readonly string $country,
        private readonly string $channel,
    ) {}

    /**
     * A reusable printed QR. The payer enters the amount.
     *
     * @param string $alias the 36 character payment address of the beneficiary
     * @param string $country ISO 3166-1 alpha-2, one of the eight member states
     */
    public static function static(string $alias, string $country): self
    {
        return new self($alias, $country, MerchantChannel::STATIC);
    }

    /** A printed QR with the amount fixed: a toll, a tariff, a pre-printed bill. */
    public static function staticWithAmount(string $alias, string $country, int $amount): self
    {
        return (new self($alias, $country, MerchantChannel::STATIC))->amount($amount);
    }

    /**
     * One transaction, generated at the till.
     *
     * The transaction id is required rather than optional here because the
     * specification requires it, and because without it two sales for the same
     * amount are indistinguishable in reconciliation.
     */
    public static function dynamic(string $alias, string $country, int $amount, string $transactionId): self
    {
        return (new self($alias, $country, MerchantChannel::DYNAMIC))
            ->amount($amount)
            ->transactionId($transactionId);
    }

    /** Presented by the payer, for collection between individuals. */
    public static function transfer(string $alias, string $country): self
    {
        return new self($alias, $country, MerchantChannel::TRANSFER);
    }

    /** Any channel, for a value the published guide does not name. */
    public static function withChannel(string $alias, string $country, string $channel): self
    {
        return new self($alias, $country, $channel);
    }

    /* ------------------------------------------------------------ options */

    /**
     * Whole francs. XOF has no minor unit, so there is nothing to round.
     */
    public function amount(int $amount): self
    {
        if ($amount < 0) {
            throw new MalformedPayload('an amount cannot be negative');
        }

        $clone = clone $this;
        $clone->amount = $amount;

        return $clone;
    }

    /**
     * The Reference Label, 62-05.
     *
     * Called a transaction id by the specification, but it is whatever the
     * beneficiary needs to tell payments apart: a till, a shop, a product
     * line, a session. It comes back on the ISO 20022 payment message, which
     * is what makes it the reconciliation handle rather than a label.
     */
    public function transactionId(string $transactionId): self
    {
        $clone = clone $this;
        $clone->transactionId = $transactionId;

        return $clone;
    }

    public function merchantName(string $name): self
    {
        $clone = clone $this;
        $clone->merchantName = $name;

        return $clone;
    }

    public function merchantCity(string $city): self
    {
        $clone = clone $this;
        $clone->merchantCity = $city;

        return $clone;
    }

    public function merchantCategory(string $category): self
    {
        $clone = clone $this;
        $clone->merchantCategory = $category;

        return $clone;
    }

    /* ------------------------------------------------------------- output */

    /**
     * The EMV string to encode into a QR image.
     *
     * Any QR library takes it from here. This package does not draw the code
     * itself, because the drawing is a solved problem in PHP and the profile
     * is not.
     */
    public function toPayload(): string
    {
        $body = Tlv::encode([
            Spec::ID_PAYLOAD_FORMAT => Spec::PAYLOAD_FORMAT_INDICATOR,
            Spec::ID_MERCHANT_ACCOUNT => Tlv::encode([
                Spec::SUB_GUI => Spec::GUI,
                Spec::SUB_ALIAS => $this->alias,
            ]),
            Spec::ID_MERCHANT_CATEGORY => $this->merchantCategory,
            Spec::ID_CURRENCY => Spec::CURRENCY,
        ]);

        if ($this->amount !== null) {
            $body .= Tlv::field(Spec::ID_AMOUNT, (string) $this->amount);
        }

        $body .= Tlv::encode([
            Spec::ID_COUNTRY => strtoupper($this->country),
            Spec::ID_MERCHANT_NAME => $this->merchantName,
            Spec::ID_MERCHANT_CITY => $this->merchantCity,
        ]);

        $additional = [];
        if ($this->transactionId !== null && $this->transactionId !== '') {
            $additional[Spec::SUB_REFERENCE_LABEL] = $this->transactionId;
        }
        $additional[Spec::SUB_MERCHANT_CHANNEL] = $this->channel;

        $body .= Tlv::field(Spec::ID_ADDITIONAL_DATA, Tlv::encode($additional));

        // The checksum covers its own id and length but not its value, so the
        // "6304" goes in before the sum is taken.
        $body .= Spec::ID_CRC.'04';

        return $body.Crc16::of($body);
    }

    /** The same payload, parsed back, for asserting on before you print it. */
    public function toPayloadObject(): Payload
    {
        return Payload::parse($this->toPayload());
    }

    public function __toString(): string
    {
        return $this->toPayload();
    }
}
