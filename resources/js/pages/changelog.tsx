import { PublicShell } from '@/components/public-shell';
import { Seo } from '@/components/seo';
import { useTranslate } from '@/lib/i18n';

/**
 * The HTML is CHANGELOG.md rendered on the server with raw HTML escaped.
 */
export default function Changelog({ html }: { html: string }) {
    const t = useTranslate();

    return (
        <PublicShell>
            <Seo
                title={t('site.changelog')}
                description={t('site.changelog_description')}
            />
            <h1 className="mb-8 text-2xl font-semibold tracking-tight">
                {t('site.changelog')}
            </h1>
            <div
                className="prose max-w-none dark:prose-invert"
                data-test="changelog-body"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </PublicShell>
    );
}
