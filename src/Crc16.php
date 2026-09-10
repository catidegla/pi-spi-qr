<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr;

/**
 * The checksum that closes a PI-SPI QR payload.
 *
 * CRC-16/CCITT-FALSE as the specification names it: ISO/IEC 13239, polynomial
 * 0x1021, initial value 0xFFFF, no final XOR and no reflection. There are half
 * a dozen CRC-16 variants that differ only in those last two, all of them
 * called "CRC-16" in casual writing, and picking the wrong one produces a
 * payload that looks correct and is rejected at the till.
 *
 * The checksum covers every data object in order, including each ID and
 * length, and including the CRC field's own ID and length ("6304"), but not
 * the four characters of the checksum itself.
 */
final class Crc16
{
    private const POLYNOMIAL = 0x1021;
    private const INITIAL = 0xFFFF;

    /** Four uppercase hex characters, as they appear in the payload. */
    public static function of(string $data): string
    {
        $crc = self::INITIAL;

        foreach (unpack('C*', $data) ?: [] as $byte) {
            $crc ^= $byte << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000)
                    ? (($crc << 1) ^ self::POLYNOMIAL) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Whether a complete payload's trailing checksum matches its contents.
     *
     * Compared case-insensitively. The specification says uppercase and every
     * generator this was tested against emits uppercase, but a reader that
     * rejects a lowercase checksum is rejecting a payload that is arithmetically
     * correct, and the reader is not the right place to enforce house style.
     */
    public static function verify(string $payload): bool
    {
        if (strlen($payload) < 8) {
            return false;
        }

        $body = substr($payload, 0, -4);

        if (! str_ends_with($body, '6304')) {
            return false;
        }

        return strcasecmp(self::of($body), substr($payload, -4)) === 0;
    }
}
