<?php

declare(strict_types=1);

namespace App\Onboarding;

/**
 * Every onboarding step in the application, in the order a person meets them.
 *
 * Discovery is one directory and one naming rule, the same deal the resource
 * registry makes: every class file in `app/Onboarding/Steps` that implements
 * {@see StepContract}. Adding a step is adding a file.
 */
final class StepRegistry
{
    /**
     * @var list<StepContract>|null
     */
    private ?array $steps = null;

    /**
     * @param  string|null  $directory  Overrides the discovery directory.
     */
    public function __construct(
        private readonly ?string $directory = null,
        private readonly string $namespace = 'App\\Onboarding\\Steps\\',
    ) {}

    /**
     * @return list<StepContract>
     */
    public function all(): array
    {
        return $this->steps ??= $this->discover();
    }

    /**
     * The steps that gate the application, as opposed to the ones that merely
     * sit on the checklist waiting to be useful.
     *
     * @return list<StepContract>
     */
    public function required(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (StepContract $step): bool => $step->isRequired(),
        ));
    }

    /**
     * @return list<StepContract>
     */
    private function discover(): array
    {
        $files = glob(mb_rtrim($this->directory ?? app_path('Onboarding/Steps'), '/').'/*.php');

        $steps = [];

        foreach ($files === false ? [] : $files as $file) {
            $class = $this->namespace.basename($file, '.php');

            if (is_a($class, StepContract::class, allow_string: true)) {
                $steps[] = new $class;
            }
        }

        usort($steps, fn (StepContract $a, StepContract $b): int => $a->order() <=> $b->order());

        return $steps;
    }
}
