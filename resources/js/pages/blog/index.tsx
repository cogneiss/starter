import { Link } from '@inertiajs/react';
import { PublicShell } from '@/components/public-shell';
import { Seo } from '@/components/seo';
import { useTranslate } from '@/lib/i18n';
import { show } from '@/routes/blog';

type PostSummary = {
    slug: string;
    title: string;
    description: string;
    date: string;
};

export default function BlogIndex({ posts }: { posts: PostSummary[] }) {
    const t = useTranslate();

    return (
        <PublicShell>
            <Seo
                title={t('site.blog')}
                description={t('site.blog_description')}
            />
            <h1 className="mb-8 text-2xl font-semibold tracking-tight">
                {t('site.blog')}
            </h1>
            {posts.length === 0 ? (
                <p className="text-muted-foreground">{t('site.blog_empty')}</p>
            ) : (
                <ul className="space-y-8" data-test="blog-posts">
                    {posts.map((post) => (
                        <li key={post.slug}>
                            <h2 className="text-lg font-medium">
                                <Link
                                    href={show(post.slug)}
                                    className="hover:underline"
                                >
                                    {post.title}
                                </Link>
                            </h2>
                            <time
                                dateTime={post.date}
                                className="text-sm text-muted-foreground"
                            >
                                {post.date}
                            </time>
                            <p className="mt-1">{post.description}</p>
                        </li>
                    ))}
                </ul>
            )}
        </PublicShell>
    );
}
