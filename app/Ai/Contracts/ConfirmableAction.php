<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * A write an agent may propose. Never a class name from model output: an agent
 * names a key, and config('ai.actions') decides which class that key means.
 *
 * The ability is checked twice — when the proposal is made, and again when the
 * person confirms it, because permissions change in between.
 */
interface ConfirmableAction
{
    /**
     * The Data object the payload is validated against.
     *
     * @return class-string<Data>
     */
    public function dataClass(): string;

    /**
     * The permission the caller needs, in the application's own vocabulary.
     */
    public function ability(): string;

    /**
     * One sentence a person can approve or reject without reading the payload.
     */
    public function summary(Data $payload): string;

    public function confirm(User $user, Data $payload): mixed;
}
