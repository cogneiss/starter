<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\BrandPalette;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Prints the palette a pair of brand hexes derives, with the measured ratio of
 * every pair in both modes.
 *
 * This is how a human checks a tenant's colours before turning them on. The
 * ratios are measured from the emitted tokens, not asserted from the code that
 * produced them, so a palette that drifts shows the drift here.
 */
#[Description('Show the palette derived from two brand hexes, with the contrast ratio of every pair')]
#[Signature('brand:preview {primary : The primary hex, e.g. #3366FF} {accent : The accent hex, e.g. #FF9900}')]
final class BrandPreviewCommand extends Command
{
    public function handle(): int
    {
        /** @var string $primary */
        $primary = $this->argument('primary');

        /** @var string $accent */
        $accent = $this->argument('accent');

        try {
            $palette = BrandPalette::from($primary, $accent);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->components->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('Palette derived from %s and %s', $primary, $accent));

        foreach ($palette as $mode => $tokens) {
            $this->newLine();
            $this->line(mb_strtoupper((string) $mode));

            foreach (BrandPalette::PAIRS as [$foreground, $background, $minimum]) {
                $ratio = BrandPalette::contrast($tokens[$foreground], $tokens[$background]);

                $this->line(sprintf(
                    '  %-32s %6.2f:1  (needs %.1f:1)  %s on %s',
                    $foreground.' on '.$background,
                    $ratio,
                    $minimum,
                    $tokens[$foreground],
                    $tokens[$background],
                ));
            }
        }

        return self::SUCCESS;
    }
}
