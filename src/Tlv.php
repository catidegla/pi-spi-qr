<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr;

use Catidegla\PiSpiQr\Exceptions\MalformedPayload;

/**
 * The tag-length-value encoding every EMV QR payload is made of.
 *
 * Each field is a two digit ID, a two digit length, then that many characters
 * of value. Fields 36 and 62 hold sub-fields encoded the same way, one level
 * deep, which is why decode() is not recursive: going deeper would invent
 * structure the specification does not have.
 *
 * The length is a count of characters, not bytes, and the two are only the
 * same while the payload stays ASCII. PI-SPI forbids personal data in the QR
 * and its own fields are all ASCII, but a merchant name is free text and a
 * caller can put an accent in it, so encode() measures bytes and rejects
 * anything non-ASCII rather than silently emitting a length no reader agrees
 * with.
 */
final class Tlv
{
    /** @param array<string, string> $fields ordered ID => value */
    public static function encode(array $fields): string
    {
        $out = '';

        foreach ($fields as $id => $value) {
            $out .= self::field((string) $id, $value);
        }

        return $out;
    }

    public static function field(string $id, string $value): string
    {
        if (strlen($id) !== 2 || ! ctype_digit($id)) {
            throw new MalformedPayload(sprintf('field id "%s" must be two digits', $id));
        }

        if (! self::isAscii($value)) {
            throw new MalformedPayload(sprintf(
                'field %s contains a non-ASCII character, and EMV lengths count characters. Transliterate it first.',
                $id,
            ));
        }

        $length = strlen($value);

        if ($length > 99) {
            throw new MalformedPayload(sprintf('field %s is %d characters, and a two digit length caps at 99', $id, $length));
        }

        return $id.str_pad((string) $length, 2, '0', STR_PAD_LEFT).$value;
    }

    /**
     * Split one level of TLV into ID => value, preserving order.
     *
     * @return array<string, string>
     */
    public static function decode(string $data): array
    {
        $fields = [];
        $offset = 0;
        $total = strlen($data);

        while ($offset < $total) {
            if ($total - $offset < 4) {
                throw new MalformedPayload(sprintf('trailing bytes at offset %d are too short to be a field', $offset));
            }

            $id = substr($data, $offset, 2);
            $rawLength = substr($data, $offset + 2, 2);

            if (! ctype_digit($id) || ! ctype_digit($rawLength)) {
                throw new MalformedPayload(sprintf('expected a numeric id and length at offset %d, found "%s%s"', $offset, $id, $rawLength));
            }

            $length = (int) $rawLength;

            if ($offset + 4 + $length > $total) {
                throw new MalformedPayload(sprintf(
                    'field %s at offset %d declares %d characters but only %d remain',
                    $id, $offset, $length, $total - $offset - 4,
                ));
            }

            // A repeated id would silently overwrite. Say so instead: it means
            // the payload is not what its author thought it was.
            if (array_key_exists($id, $fields)) {
                throw new MalformedPayload(sprintf('field %s appears more than once', $id));
            }

            $fields[$id] = substr($data, $offset + 4, $length);
            $offset += 4 + $length;
        }

        return $fields;
    }

    private static function isAscii(string $value): bool
    {
        return $value === '' || preg_match('/^[\x20-\x7E]*$/', $value) === 1;
    }
}
