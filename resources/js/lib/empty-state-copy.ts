/**
 * What an empty screen says.
 *
 * The copy is keyed rather than passed in at each call site so that the same
 * list reads the same way everywhere it appears, and so a screen that nobody
 * wrote copy for still says something useful instead of nothing at all.
 */
type EmptyStateCopy = {
    title: string;
    body: string;
    /** The one thing a person can do from here, when there is one. */
    action?: { label: string; href: string };
};

const copy: Record<string, EmptyStateCopy> = {
    'organization-members': {
        title: 'No members match',
        body: 'No member matches that search. Clear the search and the filters on screen to see everyone again.',
    },
    search: {
        title: 'Nothing matches that search',
        body: 'Try fewer words, or a name rather than a description.',
    },
    'ai-blocks': {
        title: 'Nothing to show yet',
        body: 'The assistant answered without any content to render.',
    },
};

/**
 * The key as a person would say it: `organization-invitations` reads back as
 * "organization invitations", which is enough for a default sentence that names
 * the thing the screen is missing.
 */
function humanize(key: string): string {
    return key.replaceAll('-', ' ');
}

/**
 * The copy for a screen, or a sentence built from its key.
 *
 * An unknown key is the normal case for a resource added after this file was
 * written, so the default is resource-aware rather than generic.
 */
export function emptyStateCopy(key: string): EmptyStateCopy {
    return (
        copy[key] ?? {
            title: 'Nothing here yet',
            body: `No ${humanize(key)} to show.`,
        }
    );
}
