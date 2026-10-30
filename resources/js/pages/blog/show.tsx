import { Link } from '@inertiajs/react';
import { PublicShell } from '@/components/public-shell';
import { Seo } from '@/components/seo';
import { useTranslate } from '@/lib/i18n';
import { index } from '@/routes/blog';

type Post = {
    title: string;
    description: string;
    date: string;
    author: string | null;
    html: string;
};

/**
 * The HTML was rendered on the server with raw HTML escaped and unsafe links
 * dropped, which is what makes setting it directly safe.
 */
export default function BlogShow({ post }: { post: Post }) {
    const t = useTranslate();

    return (
        <PublicShell>
            <Seo
                title={post.title}
                description={post.description}
                type="article"
            />
            <Link
                href={index()}
                className="text-sm text-muted-foreground hover:underline"
            >
                {t('site.blog_back')}
            </Link>
            <article className="mt-4">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {post.title}
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    <time dateTime={post.date}>{post.date}</time>
                    {post.author && ` · ${t('site.blog_by')} ${post.author}`}
                </p>
                <div
                    className="prose mt-8 max-w-none dark:prose-invert prose-code:before:content-none prose-code:after:content-none"
                    data-test="blog-post-body"
                    dangerouslySetInnerHTML={{ __html: post.html }}
                />
            </article>
        </PublicShell>
    );
}
