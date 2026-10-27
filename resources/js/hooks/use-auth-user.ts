import { usePage } from '@inertiajs/react';
import type { User } from '@/types/generated';

/** The signed-in user, for screens only an authenticated request can reach. */
export function useAuthUser(): User {
    const { user } = usePage().props.auth;

    if (user === null) {
        throw new Error('useAuthUser called on a guest request.');
    }

    return user;
}
