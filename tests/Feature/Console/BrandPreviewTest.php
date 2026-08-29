<?php

declare(strict_types=1);

use App\Support\BrandPalette;
use Illuminate\Support\Facades\Artisan;

it('prints a measured ratio for every pair in both modes', function (): void {
    expect(Artisan::call('brand:preview', ['primary' => '#3366FF', 'accent' => '#FF9900']))->toBe(0);

    $output = Artisan::output();

    expect(mb_substr_count($output, ':1'))->toBe(count(BrandPalette::PAIRS) * 4)
        ->and($output)->toContain('LIGHT')
        ->and($output)->toContain('DARK');
});

it('refuses a hex it cannot read', function (): void {
    $this->artisan('brand:preview', ['primary' => 'blurple', 'accent' => '#FF9900'])
        ->assertFailed();
});
