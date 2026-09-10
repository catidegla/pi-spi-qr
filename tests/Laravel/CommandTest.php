<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr\Tests\Laravel;

use Catidegla\PiSpiQr\Laravel\Facades\PiSpi;
use Catidegla\PiSpiQr\MerchantChannel;
use Catidegla\PiSpiQr\Payload;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

final class CommandTest extends TestCase
{
    private int $status = -1;

    #[Test]
    public function it_prints_a_payload_that_validates(): void
    {
        $output = $this->artisanRun('pi-spi:qr');

        $this->assertSame(0, $this->status);
        $this->assertStringContainsString('Conforms to the PI-SPI profile', $output);

        $payload = Payload::parse($this->firstLine($output));

        $this->assertSame([], $payload->problems(), implode(' | ', $payload->problems()));
        $this->assertSame(self::ALIAS, $payload->alias());
        $this->assertSame(MerchantChannel::STATIC, $payload->channel());
    }

    #[Test]
    public function amount_and_transaction_id_select_the_dynamic_builder(): void
    {
        $payload = Payload::parse($this->firstLine(
            $this->artisanRun('pi-spi:qr', ['--amount' => '1500', '--tx' => 'TILL-1-000842'])
        ));

        $this->assertSame(0, $this->status);
        $this->assertSame(1500, $payload->amount());
        $this->assertSame('TILL-1-000842', $payload->transactionId());
        $this->assertSame(MerchantChannel::DYNAMIC, $payload->channel());
    }

    #[Test]
    public function an_amount_on_its_own_stays_a_static_qr(): void
    {
        $payload = Payload::parse($this->firstLine($this->artisanRun('pi-spi:qr', ['--amount' => '2000'])));

        $this->assertSame(2000, $payload->amount());
        $this->assertSame(MerchantChannel::STATIC, $payload->channel());
    }

    #[Test]
    public function the_transfer_flag_builds_the_payer_presented_qr(): void
    {
        $payload = Payload::parse($this->firstLine($this->artisanRun('pi-spi:qr', ['--transfer' => true])));

        $this->assertSame(MerchantChannel::TRANSFER, $payload->channel());
        $this->assertFalse($payload->hasAmount());
    }

    #[Test]
    public function an_argument_reads_a_payload_instead_of_building_one(): void
    {
        $scanned = (string) PiSpi::dynamic(750, 'T-9');

        $output = $this->artisanRun('pi-spi:qr', ['payload' => $scanned]);

        $this->assertSame(0, $this->status);
        $this->assertStringContainsString('750 XOF', $output);
        $this->assertStringContainsString('T-9', $output);
        $this->assertStringContainsString('dynamic merchant QR', $output);
    }

    #[Test]
    public function a_payload_from_another_scheme_exits_non_zero_and_says_why(): void
    {
        // A Brazilian Pix code. Its own checksum is sound, so what the command
        // has to report is the profile, not a corrupt scan.
        $pix = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

        $output = $this->artisanRun('pi-spi:qr', ['payload' => $pix]);

        $this->assertSame(1, $this->status);
        $this->assertStringContainsString('not a PI-SPI QR', $output);
        $this->assertStringContainsString('settles only in 952', $output);
        $this->assertStringNotContainsString('checksum does not match', $output);
    }

    #[Test]
    public function an_unreadable_argument_exits_non_zero_rather_than_throwing(): void
    {
        $output = $this->artisanRun('pi-spi:qr', ['payload' => 'not a qr at all']);

        $this->assertSame(1, $this->status);
        $this->assertNotSame('', trim($output));
    }

    #[Test]
    public function a_non_numeric_amount_is_refused_instead_of_being_cast_to_zero(): void
    {
        $output = $this->artisanRun('pi-spi:qr', ['--amount' => '15,00']);

        $this->assertSame(1, $this->status);
        $this->assertStringContainsString('whole number of francs', $output);
    }

    #[Test]
    public function a_missing_alias_is_reported_on_the_command_not_thrown_at_the_operator(): void
    {
        config()->set('pi-spi.alias', null);

        $output = $this->artisanRun('pi-spi:qr');

        $this->assertSame(1, $this->status);
        $this->assertStringContainsString('PI_SPI_ALIAS', $output);
    }

    /** @param array<string, mixed> $arguments */
    private function artisanRun(string $command, array $arguments = []): string
    {
        $this->status = Artisan::call($command, $arguments);

        return Artisan::output();
    }

    private function firstLine(string $output): string
    {
        return trim(strtok($output, "\n") ?: '');
    }
}
