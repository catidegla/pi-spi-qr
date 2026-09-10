<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Exceptions;

use InvalidArgumentException;

/**
 * The payload could not be read as EMV TLV at all.
 *
 * Distinct from a payload that decodes cleanly and then fails PI-SPI's rules.
 * That second kind is returned by Payload::problems() as a list, because a
 * caller usually wants to see every rule a payload broke rather than only the
 * first, and because a valid EMV payload from another scheme is a normal thing
 * to encounter rather than an exceptional one.
 */
final class MalformedPayload extends InvalidArgumentException {}
