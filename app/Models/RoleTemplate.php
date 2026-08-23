<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RoleTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A blueprint for the roles every new organization gets. Editing a template
 * changes nothing for organizations that already exist.
 *
 * @property-read string $id
 * @property-read string $name
 * @property-read string $description
 * @property-read list<string> $permissions
 * @property-read bool $is_default
 * @property-read bool $protected
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class RoleTemplate extends Model
{
    /** @use HasFactory<RoleTemplateFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'description' => 'string',
            'permissions' => 'array',
            'is_default' => 'boolean',
            'protected' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
