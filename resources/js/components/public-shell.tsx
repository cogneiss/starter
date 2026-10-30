import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslate } from '@/lib/i18n';
import { changelog, home } from '@/routes';
import { index as blogIndex } from '@/routes/blog';

/**
 * The frame the public pages share: a small nav and a readable column.
 */
export function PublicShell({ children }: { children: ReactNode }) {
    const t = useTranslate();

    return (
        <div className="min-h-screen bg-background text-foreground">
            <header className="border-b border-border">
                <nav
                    aria-label={t('site.nav')}
                    className="mx-auto flex max-w-3xl items-center gap-6 px-6 py-4 text-sm"
                >
                    <Link href={home()} className="font-medium">
                        {t('site.home')}
                    </Link>
                    <Link href={blogIndex()} className="hover:underline">
                        {t('site.blog')}
                    </Link>
                    <Link href={changelog()} className="hover:underline">
                        {t('site.changelog')}
                    </Link>
                </nav>
            </header>
            <main className="mx-auto max-w-3xl px-6 py-10">{children}</main>
        </div>
    );
}
