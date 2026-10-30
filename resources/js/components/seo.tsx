import { Head, usePage } from '@inertiajs/react';

type SeoProps = {
    title: string;
    description: string;
    type?: 'website' | 'article';
};

/**
 * The head tags a search engine and a link preview read: description,
 * canonical URL, Open Graph and Twitter card.
 *
 * The canonical URL is the configured application URL plus the current path
 * with no query string, so a tracking parameter never becomes a second page.
 * With the SSR server running these tags are in the first HTML response; without
 * it they arrive when the client renders.
 */
export function Seo({ title, description, type = 'website' }: SeoProps) {
    const {
        props: { appUrl, name },
        url,
    } = usePage();
    const path = url.split('?')[0];
    const canonical = `${appUrl.replace(/\/$/, '')}${path}`;

    return (
        <Head title={title}>
            <meta
                head-key="description"
                name="description"
                content={description}
            />
            <link head-key="canonical" rel="canonical" href={canonical} />
            <meta head-key="og:title" property="og:title" content={title} />
            <meta
                head-key="og:description"
                property="og:description"
                content={description}
            />
            <meta head-key="og:type" property="og:type" content={type} />
            <meta head-key="og:url" property="og:url" content={canonical} />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={name}
            />
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content="summary"
            />
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={title}
            />
            <meta
                head-key="twitter:description"
                name="twitter:description"
                content={description}
            />
        </Head>
    );
}
