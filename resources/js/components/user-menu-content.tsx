import { Link, router, usePage } from '@inertiajs/react';
import { Check, Languages, LogOut, Settings } from 'lucide-react';
import UserLocaleController from '@/actions/App/Http/Controllers/UserLocaleController';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useTranslate } from '@/lib/i18n';
import { logout } from '@/routes';
import { edit } from '@/routes/user-profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const t = useTranslate();
    const { locale, supportedLocales } = usePage().props;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const handleLocale = (code: string) => {
        router.put(UserLocaleController.url(), { locale: code });
    };

    return (
        <>
            <DropdownMenuGroup>
                <DropdownMenuLabel className="p-0 font-normal">
                    <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                        <UserInfo user={user} showEmail={true} />
                    </div>
                </DropdownMenuLabel>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem
                    render={
                        <Link
                            className="block w-full cursor-pointer"
                            href={edit()}
                            prefetch
                            onClick={cleanup}
                        />
                    }
                >
                    <Settings className="mr-2" />
                    Settings
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                    <Languages className="mr-2 inline size-3.5" />
                    {t('language')}
                </DropdownMenuLabel>
                {supportedLocales.map((code) => (
                    <DropdownMenuItem
                        key={code}
                        className="cursor-pointer"
                        data-test={`locale-${code}`}
                        onClick={() => handleLocale(code)}
                    >
                        <Check
                            className={
                                code === locale ? 'mr-2' : 'mr-2 opacity-0'
                            }
                        />
                        {t(`locale.${code}`)}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                render={
                    <Link
                        className="block w-full cursor-pointer"
                        href={logout()}
                        as="button"
                        onClick={handleLogout}
                        data-test="logout-button"
                    />
                }
            >
                <LogOut className="mr-2" />
                Log out
            </DropdownMenuItem>
        </>
    );
}
