<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Seeder;
use Database\Seeders\RoleTemplateSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Role templates are part of the schema in practice: an organization
     * cannot be created without them, so every test gets them.
     */
    protected bool $seed = true;

    /** @var class-string<Seeder> */
    protected $seeder = RoleTemplateSeeder::class;
}
