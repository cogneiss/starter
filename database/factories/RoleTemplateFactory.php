<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RoleTemplate;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleTemplate>
 */
final class RoleTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
            'permissions' => PermissionCatalog::endingWith('view'),
            'is_default' => false,
            'protected' => false,
        ];
    }
}
