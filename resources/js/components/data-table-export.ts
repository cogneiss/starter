import { useState } from 'react';

/**
 * Downloads the list's current query as CSV, and says when it has.
 */
export function useCsvExport(
    path: string,
    label: string,
    onFailed: (failed: boolean) => void,
) {
    const [exported, setExported] = useState(false);

    /**
     * The export asks the list endpoint it is already looking at for the same
     * query in another representation, so there is no second endpoint to keep
     * in step and no chance of exporting a different set of rows than the one
     * on screen. `Accept` is what makes it CSV; without that header the server
     * answers with the page and the download never appears.
     */
    async function exportCsv() {
        setExported(false);

        const response = await fetch(path + window.location.search, {
            headers: { Accept: 'text/csv' },
        });

        if (!response.headers.get('content-type')?.startsWith('text/csv')) {
            onFailed(true);

            return;
        }

        const url = URL.createObjectURL(await response.blob());
        const anchor = document.createElement('a');

        anchor.href = url;
        anchor.download = `${label.toLowerCase()}.csv`;
        anchor.click();

        // Released on the next tick: revoking it in the same one cancels the
        // download the click just started.
        window.setTimeout(() => URL.revokeObjectURL(url), 0);

        setExported(true);
    }

    return { exported, exportCsv };
}
