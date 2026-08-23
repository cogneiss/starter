<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

final readonly class CreateUser
{
    public function __construct(private CreateOrganization $organizations) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, #[SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($attributes, $password): User {
            $user = User::query()->create([
                ...$attributes,
                'password' => $password,
            ]);

            $this->organizations->handle($user, $user->name, personal: true);

            event(new Registered($user));

            return $user;
        });
    }
}
