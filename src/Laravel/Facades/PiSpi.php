<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Laravel\Facades;

use Catidegla\PiSpiQr\Laravel\PiSpi as Builder;
use Catidegla\PiSpiQr\Payload;
use Catidegla\PiSpiQr\QrCode;
use Illuminate\Support\Facades\Facade;

/**
 * @method static QrCode static(?string $alias = null, ?string $country = null)
 * @method static QrCode staticWithAmount(int $amount, ?string $alias = null, ?string $country = null)
 * @method static QrCode dynamic(int $amount, string $transactionId, ?string $alias = null, ?string $country = null)
 * @method static QrCode transfer(?string $alias = null, ?string $country = null)
 * @method static Payload parse(string $payload)
 * @method static bool isValid(string $payload)
 * @method static list<string> problems(string $payload)
 *
 * @see Builder
 */
class PiSpi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'pi-spi';
    }
}
