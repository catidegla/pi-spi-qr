<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Laravel;

use Catidegla\PiSpiQr\Exceptions\MalformedPayload;
use Catidegla\PiSpiQr\Payload;
use Catidegla\PiSpiQr\QrCode;
use Catidegla\PiSpiQr\Spec;

/**
 * The framework-agnostic builder with your configuration already filled in.
 *
 * Every QR a given merchant issues repeats the same four values: alias,
 * country, name and city. Making the caller pass them at each till, on each
 * invoice, in each job, is how one of them ends up wrong in one code path and
 * nowhere else. So they come from config once and the call site carries only
 * what actually varies, which is the amount and the transaction id.
 *
 * Every method still returns the core QrCode object, so anything the bridge
 * does not cover is one fluent call away and nothing is hidden behind it.
 */
class PiSpi
{
    /** @param array{alias?: ?string, country?: ?string, merchant?: array{name?: string, city?: string, category?: string}} $config */
    public function __construct(private readonly array $config) {}

    /** A reusable printed QR. The payer enters the amount. */
    public function static(?string $alias = null, ?string $country = null): QrCode
    {
        return $this->dress(QrCode::static($this->alias($alias), $this->country($country)));
    }

    /** A printed QR with the amount fixed: a toll, a tariff, a pre-printed bill. */
    public function staticWithAmount(int $amount, ?string $alias = null, ?string $country = null): QrCode
    {
        return $this->dress(QrCode::staticWithAmount($this->alias($alias), $this->country($country), $amount));
    }

    /**
     * One transaction, generated at the till.
     *
     * The transaction id stays required here even though everything else
     * became optional, because it is the one field config cannot supply and
     * the one the specification will not do without.
     */
    public function dynamic(int $amount, string $transactionId, ?string $alias = null, ?string $country = null): QrCode
    {
        return $this->dress(QrCode::dynamic($this->alias($alias), $this->country($country), $amount, $transactionId));
    }

    /** Presented by the payer, for collection between individuals. */
    public function transfer(?string $alias = null, ?string $country = null): QrCode
    {
        return $this->dress(QrCode::transfer($this->alias($alias), $this->country($country)));
    }

    /** Read a scanned payload. Throws only when the string is not EMV at all. */
    public function parse(string $payload): Payload
    {
        return Payload::parse($payload);
    }

    /**
     * Whether a scanned payload is a conforming PI-SPI QR.
     *
     * Swallows a malformed payload into false rather than an exception,
     * because at a till "this did not scan properly" and "this is somebody
     * else's QR" are the same outcome for the person holding the phone.
     */
    public function isValid(string $payload): bool
    {
        try {
            return Payload::parse($payload)->isValid();
        } catch (MalformedPayload) {
            return false;
        }
    }

    /** @return list<string> every rule the payload breaks, empty when it conforms */
    public function problems(string $payload): array
    {
        try {
            return Payload::parse($payload)->problems();
        } catch (MalformedPayload $e) {
            return [$e->getMessage()];
        }
    }

    private function dress(QrCode $qr): QrCode
    {
        $merchant = (array) ($this->config['merchant'] ?? []);

        return $qr
            ->merchantName((string) ($merchant['name'] ?? 'X'))
            ->merchantCity((string) ($merchant['city'] ?? 'X'))
            ->merchantCategory((string) ($merchant['category'] ?? '0000'));
    }

    private function alias(?string $override): string
    {
        $alias = $override ?? $this->config['alias'] ?? null;

        if ($alias === null || $alias === '') {
            throw new MalformedPayload(
                'no PI-SPI alias configured. Set PI_SPI_ALIAS to the 36 character payment address your '.
                'institution issued, or pass one to this call.',
            );
        }

        return $alias;
    }

    private function country(?string $override): string
    {
        $country = $override ?? $this->config['country'] ?? null;

        if ($country === null || $country === '') {
            throw new MalformedPayload(
                'no PI-SPI country configured. Set PI_SPI_COUNTRY to your member state, one of '.
                implode(', ', Spec::COUNTRIES).', or pass one to this call.',
            );
        }

        return $country;
    }
}
