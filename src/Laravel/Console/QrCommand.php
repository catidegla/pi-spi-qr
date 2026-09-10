<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Laravel\Console;

use Catidegla\PiSpiQr\Exceptions\MalformedPayload;
use Catidegla\PiSpiQr\Laravel\PiSpi;
use Catidegla\PiSpiQr\MerchantChannel;
use Illuminate\Console\Command;

/**
 * Build or read a payload from the terminal.
 *
 * Exists because the failure people actually hit is a QR that scans and then
 * is refused, and diagnosing that means reading the payload rather than
 * looking at the picture. Being able to paste a string and see which rule it
 * breaks turns a support conversation into one command.
 */
class QrCommand extends Command
{
    protected $signature = 'pi-spi:qr
        {payload? : An existing payload to inspect instead of building one}
        {--amount= : Amount in whole francs, XOF has no minor unit}
        {--tx= : Transaction id for the Reference Label, required for a dynamic QR}
        {--alias= : Override the configured beneficiary alias}
        {--country= : Override the configured country}
        {--transfer : Build a transfer QR, presented by the payer}';

    protected $description = 'Build a PI-SPI QR payload, or inspect one that was scanned';

    public function handle(PiSpi $piSpi): int
    {
        $payload = $this->argument('payload');

        return $payload === null
            ? $this->build($piSpi)
            : $this->inspect($piSpi, (string) $payload);
    }

    private function build(PiSpi $piSpi): int
    {
        $amount = $this->option('amount');
        $tx = $this->option('tx');
        $alias = $this->option('alias');
        $country = $this->option('country');

        if ($amount !== null && ! ctype_digit((string) $amount)) {
            $this->error(sprintf('--amount must be a whole number of francs, "%s" is not one.', $amount));

            return self::FAILURE;
        }

        try {
            $qr = match (true) {
                (bool) $this->option('transfer') => $piSpi->transfer($alias, $country),
                $amount !== null && $tx !== null => $piSpi->dynamic((int) $amount, (string) $tx, $alias, $country),
                $amount !== null => $piSpi->staticWithAmount((int) $amount, $alias, $country),
                default => $piSpi->static($alias, $country),
            };

            $built = $qr->toPayload();
        } catch (MalformedPayload $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($built);
        $this->newLine();

        return $this->inspect($piSpi, $built, building: true);
    }

    private function inspect(PiSpi $piSpi, string $raw, bool $building = false): int
    {
        try {
            $payload = $piSpi->parse($raw);
        } catch (MalformedPayload $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $channel = $payload->channel();

        $this->table(['field', 'value'], [
            ['scheme', $payload->gui() ?? '(missing)'],
            ['alias', $payload->alias() ?? '(missing)'],
            ['country', $payload->country() ?? '(missing)'],
            ['amount', $payload->hasAmount() ? $payload->amount().' XOF' : 'entered by the payer'],
            ['transaction id', $payload->transactionId() ?? '(none)'],
            ['channel', $channel === null ? '(missing)' : MerchantChannel::describe($channel)],
            ['merchant', trim(($payload->merchantName() ?? '').', '.($payload->merchantCity() ?? ''), ', ')],
            ['checksum', $payload->checksum() ?? '(missing)'],
        ]);

        $problems = $payload->problems();

        if ($problems === []) {
            $this->info($building ? 'Conforms to the PI-SPI profile.' : 'This payload conforms to the PI-SPI profile.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(sprintf('%d problem(s):', count($problems)));
        foreach ($problems as $problem) {
            $this->line('  - '.$problem);
        }

        return self::FAILURE;
    }
}
