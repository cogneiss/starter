<?php

declare(strict_types=1);

use App\Onboarding\StepContract;
use App\Onboarding\StepRegistry;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->base = sys_get_temp_dir().'/make-onboarding-step-'.getmypid();

    File::deleteDirectory($this->base);
});

afterEach(function (): void {
    File::deleteDirectory($this->base);
});

it('MakeOnboardingStep writes a step the registry can pick up', function (): void {
    $this->artisan('app:make-onboarding-step', ['name' => 'ConnectBilling', '--base' => $this->base])
        ->assertSuccessful();

    $path = $this->base.'/app/Onboarding/Steps/ConnectBillingStep.php';

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))
        ->toContain('final class ConnectBillingStep implements StepContract')
        ->toContain("return 'connect-billing';")
        ->toContain("return 'Connect Billing';");
});

it('MakeOnboardingStep refuses a name that is not a class name', function (): void {
    $this->artisan('app:make-onboarding-step', ['name' => 'connect billing!', '--base' => $this->base])
        ->assertFailed();

    expect(File::isDirectory($this->base))->toBeFalse();
});

it('MakeOnboardingStep will not overwrite an existing step without being told to', function (): void {
    $this->artisan('app:make-onboarding-step', ['name' => 'ConnectBilling', '--base' => $this->base])
        ->assertSuccessful();

    File::put($this->base.'/app/Onboarding/Steps/ConnectBillingStep.php', '<?php // mine');

    $this->artisan('app:make-onboarding-step', ['name' => 'ConnectBilling', '--base' => $this->base])
        ->assertFailed();

    expect(File::get($this->base.'/app/Onboarding/Steps/ConnectBillingStep.php'))->toBe('<?php // mine');

    $this->artisan('app:make-onboarding-step', ['name' => 'ConnectBilling', '--base' => $this->base, '--force' => true])
        ->assertSuccessful();

    expect(File::get($this->base.'/app/Onboarding/Steps/ConnectBillingStep.php'))->toContain('ConnectBillingStep implements');
});

it('MakeOnboardingStep discovers the shipped steps in the order people meet them', function (): void {
    $steps = new StepRegistry;

    expect(array_map(fn (StepContract $step): string => $step->key(), $steps->all()))
        ->toBe(['brand', 'invite', 'two-factor'])
        ->and(array_map(fn (StepContract $step): string => $step->key(), $steps->required()))
        ->toBe(['brand', 'invite']);
});
