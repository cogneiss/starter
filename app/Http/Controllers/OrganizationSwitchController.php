<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SwitchOrganization;
use App\Http\Requests\UpdateOrganizationSwitchRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class OrganizationSwitchController
{
    public function update(UpdateOrganizationSwitchRequest $request, #[CurrentUser] User $user, SwitchOrganization $action): RedirectResponse
    {
        $organization = Organization::query()->findOrFail($request->string('organization')->value());

        $action->handle($user, $organization);

        return back();
    }
}
