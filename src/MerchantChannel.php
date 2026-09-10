<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr;

/**
 * Sub-field 62-11, which says what kind of QR this is.
 *
 * Deliberately not an enum. The guide documents three values, and the
 * specification's own worked example carries a fourth, "500", which is
 * documented nowhere on the portal. An enum would reject BCEAO's own test
 * vector as invalid, so any three digit value is accepted and the named ones
 * are constants rather than a closed set.
 *
 * If that fourth value turns out to be a typo in their example, nothing here
 * needs to change. If it turns out to be a channel they forgot to document,
 * nothing here needs to change either. That is the point.
 */
final class MerchantChannel
{
    /** Reusable printed support: a counter, a shop window, a pre-printed bill. */
    public const STATIC = '000';

    /** One transaction, generated at the till. Requires a unique TxID. */
    public const DYNAMIC = '400';

    /** Presented by the payer, for collection between individuals. */
    public const TRANSFER = '731';

    /** Present in the specification's worked example and documented nowhere. */
    public const UNDOCUMENTED_IN_EXAMPLE = '500';

    /** @return list<string> the ones the portal actually describes */
    public static function documented(): array
    {
        return [self::STATIC, self::DYNAMIC, self::TRANSFER];
    }

    public static function isWellFormed(string $channel): bool
    {
        return strlen($channel) === 3 && ctype_digit($channel);
    }

    public static function describe(string $channel): string
    {
        return match ($channel) {
            self::STATIC => 'static merchant QR',
            self::DYNAMIC => 'dynamic merchant QR',
            self::TRANSFER => 'transfer QR presented by the payer',
            default => sprintf('channel %s, not described in the published guide', $channel),
        };
    }
}
