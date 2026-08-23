import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import BrowserSessionController from '@/actions/App/Http/Controllers/BrowserSessionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { show } from '@/routes/browser-session';
import type {
    BreadcrumbItem,
    BrowserSession,
    LoginHistoryEntry,
} from '@/types';

type Props = {
    sessions: BrowserSession[];
    logins: LoginHistoryEntry[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Browser sessions',
        href: show(),
    },
];

export default function Show({ sessions, logins }: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Browser sessions" />

            <h1 className="sr-only">Browser sessions</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Browser sessions"
                        description="Devices that are signed in to your account"
                    />

                    {sessions.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No other browser sessions are recorded.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {sessions.map((session) => (
                                <li
                                    key={session.id}
                                    className="flex items-center justify-between gap-4 rounded-lg border p-4"
                                    data-test="browser-session"
                                >
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-medium">
                                            {session.device}
                                        </p>

                                        <p className="text-sm text-muted-foreground">
                                            {session.ip_address ??
                                                'Unknown address'}{' '}
                                            &middot; {session.last_active_diff}
                                        </p>
                                    </div>

                                    {session.current && (
                                        <Badge variant="secondary">
                                            This device
                                        </Badge>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <Dialog>
                        <DialogTrigger
                            render={
                                <Button
                                    variant="destructive"
                                    data-test="log-out-other-sessions-button"
                                />
                            }
                        >
                            Log out other sessions
                        </DialogTrigger>

                        <DialogContent>
                            <DialogTitle>Log out other sessions</DialogTitle>

                            <DialogDescription>
                                Enter your password to sign out of every other
                                browser. This device stays signed in.
                            </DialogDescription>

                            <Form
                                {...BrowserSessionController.destroy.form()}
                                options={{ preserveScroll: true }}
                                onError={() => passwordInput.current?.focus()}
                                resetOnSuccess
                                className="space-y-6"
                            >
                                {({
                                    resetAndClearErrors,
                                    processing,
                                    errors,
                                }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="password"
                                                className="sr-only"
                                            >
                                                Password
                                            </Label>

                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                ref={passwordInput}
                                                placeholder="Password"
                                                autoComplete="current-password"
                                            />

                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <DialogClose
                                                render={
                                                    <Button
                                                        variant="secondary"
                                                        onClick={() =>
                                                            resetAndClearErrors()
                                                        }
                                                    />
                                                }
                                            >
                                                Cancel
                                            </DialogClose>

                                            <Button
                                                variant="destructive"
                                                disabled={processing}
                                                type="submit"
                                                data-test="confirm-log-out-other-sessions-button"
                                            >
                                                Log out other sessions
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>

                    <Heading
                        variant="small"
                        title="Recent sign-ins"
                        description="The last ten attempts on your account"
                    />

                    {logins.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No sign-ins are recorded yet.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {logins.map((login) => (
                                <li
                                    key={login.id}
                                    className="flex items-center justify-between gap-4 rounded-lg border p-4"
                                    data-test="login-history-entry"
                                >
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-medium">
                                            {login.device}
                                        </p>

                                        <p className="text-sm text-muted-foreground">
                                            {login.ip_address ??
                                                'Unknown address'}{' '}
                                            &middot; {login.created_at_diff}
                                        </p>
                                    </div>

                                    <Badge
                                        variant={
                                            login.successful
                                                ? 'secondary'
                                                : 'destructive'
                                        }
                                    >
                                        {login.successful
                                            ? 'Successful'
                                            : 'Failed'}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
