import { usePage } from '@inertiajs/react';

/**
 * The active locale's strings, as the request sent them.
 *
 * There is no second copy of any string on the client. The PHP translation
 * files are canonical; this module only reads what the page prop carries.
 */
export function translate(
    translations: Record<string, string>,
    key: string,
): string {
    const message = translations[key];

    // A key nobody has translated yet renders as itself. Blank would be worse:
    // an untranslated button is a button with no words on it, and nothing on
    // the screen would say which string went missing.
    if (typeof message !== 'string') {
        return key;
    }

    return message;
}

/**
 * The translator for the current page.
 */
export function useTranslate(): (key: string) => string {
    const { translations } = usePage().props;

    return (key: string) => translate(translations, key);
}
