import { router } from '@inertiajs/react';
import { BookmarkPlus, Pencil, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, show, store, update } from '@/routes/saved-search';
import type { SavedSearch } from '@/types/generated';

/**
 * A list query as the address bar and a saved view both hold it: the same shape
 * on the way out to the server and on the way back into a stored search.
 */
export type QueryParameters = Record<
    string,
    string | number | Record<string, string | string[] | Record<string, string>>
>;

type ViewsProps = {
    /** This person's kept views of this list, as the server sent them. */
    searches: SavedSearch[];
    /** The resource key a new view is saved against. */
    resource: string;
    /** The query on screen right now, ready to be written down. */
    current: QueryParameters;
};

/**
 * The views a person has kept of one list.
 *
 * Applying one is a visit to the saved search, which answers with a redirect to
 * the list carrying the saved query in the address bar. The view therefore ends
 * up somewhere it can be shared, reloaded and stepped back out of, rather than
 * in a piece of component state that only this tab knows about.
 */
export function DataTableViews({ searches, resource, current }: ViewsProps) {
    const [naming, setNaming] = useState<SavedSearch | 'new' | null>(null);
    const [name, setName] = useState('');

    function open(target: SavedSearch | 'new') {
        setName(target === 'new' ? '' : target.name);
        setNaming(target);
    }

    function save() {
        if (naming === 'new') {
            router.post(
                store().url,
                { resource, name, query: current },
                { preserveScroll: true },
            );
        } else if (naming !== null) {
            router.patch(
                update(naming.id).url,
                { name },
                { preserveScroll: true },
            );
        }

        setNaming(null);
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            size="sm"
                            variant="outline"
                            data-test="table-views"
                        />
                    }
                >
                    <BookmarkPlus className="size-4" />
                    Views
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-72">
                    {searches.length === 0 && (
                        <p className="px-2 py-1.5 text-sm text-muted-foreground">
                            No saved views yet.
                        </p>
                    )}

                    {searches.map((search) => (
                        <div
                            key={search.id}
                            className="flex items-center gap-1 px-1 py-0.5"
                        >
                            <Button
                                size="sm"
                                variant="ghost"
                                className="flex-1 justify-start"
                                data-test={`view-${search.id}`}
                                onClick={() => router.get(show(search.id).url)}
                            >
                                {search.name}
                                {search.isDefault && (
                                    <Star className="size-3 fill-current" />
                                )}
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label={`Make ${search.name} the default view`}
                                data-test={`view-default-${search.id}`}
                                onClick={() =>
                                    router.patch(
                                        update(search.id).url,
                                        { is_default: true },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Star className="size-4" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label={`Rename ${search.name}`}
                                data-test={`view-rename-${search.id}`}
                                onClick={() => open(search)}
                            >
                                <Pencil className="size-4" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label={`Delete ${search.name}`}
                                data-test={`view-delete-${search.id}`}
                                onClick={() =>
                                    router.delete(destroy(search.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}

                    <Button
                        size="sm"
                        variant="ghost"
                        className="w-full justify-start"
                        data-test="view-save"
                        onClick={() => open('new')}
                    >
                        <BookmarkPlus className="size-4" />
                        Save current view
                    </Button>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={naming !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        setNaming(null);
                    }
                }}
            >
                <DialogContent data-test="view-dialog">
                    <DialogTitle>
                        {naming === 'new' ? 'Save this view' : 'Rename view'}
                    </DialogTitle>
                    <DialogDescription>
                        The search, sort and filters on screen right now.
                    </DialogDescription>
                    <Label htmlFor="view-name">Name</Label>
                    <Input
                        id="view-name"
                        data-test="view-name"
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                    />
                    <DialogFooter className="gap-2">
                        <DialogClose
                            render={
                                <Button
                                    variant="secondary"
                                    data-test="view-cancel"
                                />
                            }
                        >
                            Cancel
                        </DialogClose>
                        <Button
                            data-test="view-confirm"
                            disabled={name.trim() === ''}
                            onClick={save}
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
