<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Bot friction for the public forms: a honeypot field a person never sees and
 * a signed timestamp issued when the form was rendered. A submission that
 * fills the honeypot, comes back faster than a person can type, or carries a
 * token older than the window is not from the form we served.
 */
final readonly class FormFriction
{
    public function token(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * What a form page needs to render the friction fields.
     *
     * @return array{field: string, token: string}
     */
    public function props(): array
    {
        return [
            'field' => config()->string('security.friction.field'),
            'token' => $this->token(),
        ];
    }

    /**
     * The name of the check the request tripped, or null when it passes.
     */
    public function tripped(Request $request): ?string
    {
        if ($request->filled(config()->string('security.friction.field'))) {
            return 'honeypot';
        }

        try {
            $issued = (int) Crypt::decryptString($request->string('_friction')->value());
        } catch (DecryptException) {
            return 'token';
        }

        $age = now()->getTimestamp() - $issued;

        if ($age < config()->integer('security.friction.min_seconds')) {
            return 'dwell';
        }

        if ($age > config()->integer('security.friction.max_age_seconds')) {
            return 'age';
        }

        return null;
    }
}
