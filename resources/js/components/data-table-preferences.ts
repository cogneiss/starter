/**
 * The version in the preferences key. Column preferences are stored per person
 * per screen, and the shape they are stored in will change. Reading an old
 * shape back into a new table is how a saved preference turns into a blank
 * screen, so the version goes in the key: a shape change orphans the old entry
 * instead of misreading it.
 */
export const PREFERENCES_VERSION = 'v1';

/** What a person has arranged for themselves on one list screen. */
export type Preferences = {
    hidden: string[];
    widths: Record<string, number>;
};

/**
 * What this person last arranged on this screen, or nothing at all. Anything
 * unreadable is nothing at all: a corrupted entry is not worth a broken table.
 */
export function readPreferences(key: string): Preferences {
    const empty: Preferences = { hidden: [], widths: {} };

    try {
        const stored = window.localStorage.getItem(key);

        if (stored === null) {
            return empty;
        }

        const parsed: unknown = JSON.parse(stored);

        if (typeof parsed !== 'object' || parsed === null) {
            return empty;
        }

        const hidden: unknown = 'hidden' in parsed ? parsed.hidden : [];
        const widths: unknown = 'widths' in parsed ? parsed.widths : {};

        return {
            hidden: Array.isArray(hidden)
                ? hidden.filter((id): id is string => typeof id === 'string')
                : [],
            widths:
                typeof widths === 'object' && widths !== null
                    ? Object.fromEntries(
                          Object.entries(widths).filter(
                              (entry): entry is [string, number] =>
                                  typeof entry[1] === 'number',
                          ),
                      )
                    : {},
        };
    } catch {
        return empty;
    }
}
