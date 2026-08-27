import { SlidersHorizontal, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { ResourceFilter } from '@/types/generated';

/** A filter value as the server normalised it, or nothing selected. */
type FilterValue = ResourceFilter['value'];

/** What the trigger says when a select filter is not narrowing anything. */
const ANY = '__any';

type FiltersProps = {
    filters: ResourceFilter[];
    /** Applies one filter; `null` clears it. */
    onChange: (key: string, value: FilterValue) => void;
    onClear: () => void;
};

/**
 * The filter controls for a list, in the two shapes a screen needs them.
 *
 * The facets themselves come from the server — their types, their options and
 * the count beside each option — so a page never invents a filter the query
 * layer would not honour. The wide layout puts them beside the search box; the
 * narrow one puts them behind a button, because five controls in a row is not a
 * phone screen.
 */
export function DataTableFilters({ filters, onChange, onClear }: FiltersProps) {
    const [open, setOpen] = useState(false);

    if (filters.length === 0) {
        return null;
    }

    const applied = filters.filter((filter) => filter.value !== null).length;

    const controls = filters.map((filter) => (
        <FilterControl key={filter.key} filter={filter} onChange={onChange} />
    ));

    return (
        <>
            <div
                className="hidden flex-wrap items-end gap-3 md:flex"
                data-test="table-filters"
            >
                {controls}

                {applied > 0 && (
                    <Button
                        size="sm"
                        variant="ghost"
                        data-test="filters-clear"
                        onClick={onClear}
                    >
                        <X className="size-3.5" />
                        Clear filters
                    </Button>
                )}
            </div>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetTrigger
                    render={
                        <Button
                            variant="outline"
                            size="sm"
                            className="w-fit md:hidden"
                            data-test="filters-open"
                        >
                            <SlidersHorizontal className="size-3.5" />
                            Filters
                            {applied > 0 && (
                                <Badge data-test="filters-applied">
                                    {applied}
                                </Badge>
                            )}
                        </Button>
                    }
                />

                <SheetContent side="bottom" data-test="filters-sheet">
                    <SheetHeader>
                        <SheetTitle>Filters</SheetTitle>
                        <SheetDescription>
                            Narrow the list. Every choice is kept in the address
                            bar, so the view can be shared as it looks.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-col gap-4 px-4 pb-6">
                        {controls}

                        {applied > 0 && (
                            <Button
                                variant="outline"
                                data-test="filters-clear-all"
                                onClick={() => {
                                    onClear();
                                    setOpen(false);
                                }}
                            >
                                Clear all filters
                            </Button>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

type ControlProps = {
    filter: ResourceFilter;
    onChange: (key: string, value: FilterValue) => void;
};

function FilterControl({ filter, onChange }: ControlProps) {
    switch (filter.type) {
        case 'multi-select':
            return <MultiSelectFilter filter={filter} onChange={onChange} />;
        case 'range':
            return (
                <BoundsFilter
                    filter={filter}
                    onChange={onChange}
                    type="number"
                    bounds={['min', 'max']}
                    labels={['Min', 'Max']}
                />
            );
        case 'date-range':
            return (
                <BoundsFilter
                    filter={filter}
                    onChange={onChange}
                    type="date"
                    bounds={['from', 'to']}
                    labels={['From', 'To']}
                />
            );
        default:
            return <ChoiceFilter filter={filter} onChange={onChange} />;
    }
}

/**
 * A select and a boolean are the same control: one choice out of a short list,
 * plus the choice to say nothing. A boolean's options arrive as `1` and `0`
 * already labelled, so nothing here has to know which it is drawing.
 */
function ChoiceFilter({ filter, onChange }: ControlProps) {
    const value = typeof filter.value === 'string' ? filter.value : null;
    const selected =
        typeof filter.value === 'boolean'
            ? filter.value
                ? '1'
                : '0'
            : (value ?? ANY);

    return (
        <Field filter={filter}>
            <Select
                value={selected}
                onValueChange={(next: string | null) =>
                    onChange(
                        filter.key,
                        next === null || next === ANY
                            ? null
                            : filter.type === 'boolean'
                              ? next === '1'
                              : next,
                    )
                }
            >
                <SelectTrigger
                    className="w-full min-w-40"
                    data-test={`filter-${filter.key}`}
                    aria-label={filter.label}
                >
                    <SelectValue />
                </SelectTrigger>

                <SelectContent>
                    <SelectItem value={ANY}>Any</SelectItem>

                    {filter.options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label} ({option.count})
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

function MultiSelectFilter({ filter, onChange }: ControlProps) {
    const chosen = Array.isArray(filter.value) ? filter.value : [];

    return (
        <Field filter={filter}>
            <div
                className="flex flex-wrap gap-3"
                role="group"
                aria-label={filter.label}
                data-test={`filter-${filter.key}`}
            >
                {filter.options.map((option) => (
                    <Label
                        key={option.value}
                        className="flex items-center gap-2 font-normal"
                    >
                        <Checkbox
                            checked={chosen.includes(option.value)}
                            data-test={`filter-${filter.key}-${option.value}`}
                            onCheckedChange={(checked: boolean) => {
                                const next = checked
                                    ? [...chosen, option.value].sort()
                                    : chosen.filter(
                                          (value) => value !== option.value,
                                      );

                                onChange(
                                    filter.key,
                                    next.length === 0 ? null : next,
                                );
                            }}
                        />
                        {option.label} ({option.count})
                    </Label>
                ))}
            </div>
        </Field>
    );
}

type BoundsProps = ControlProps & {
    type: 'number' | 'date';
    bounds: [string, string];
    labels: [string, string];
};

/**
 * Two inputs that mean one filter. A bound left empty is left out of the URL,
 * so "from January, no end" is a filter rather than an incomplete form.
 */
function BoundsFilter({ filter, onChange, type, bounds, labels }: BoundsProps) {
    const current =
        filter.value !== null &&
        typeof filter.value === 'object' &&
        !Array.isArray(filter.value)
            ? filter.value
            : {};

    function set(bound: string, value: string) {
        const next: Record<string, string | number> = { ...current };

        if (value === '') {
            delete next[bound];
        } else {
            next[bound] = value;
        }

        onChange(filter.key, Object.keys(next).length === 0 ? null : next);
    }

    return (
        <Field filter={filter}>
            <div className="flex items-center gap-2">
                {bounds.map((bound, index) => (
                    <Input
                        key={bound}
                        type={type}
                        className="w-32"
                        aria-label={`${filter.label} ${labels[index]}`}
                        placeholder={labels[index]}
                        data-test={`filter-${filter.key}-${bound}`}
                        value={String(current[bound] ?? '')}
                        onChange={(event) => set(bound, event.target.value)}
                    />
                ))}
            </div>
        </Field>
    );
}

function Field({
    filter,
    children,
}: {
    filter: ResourceFilter;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-xs font-medium text-muted-foreground">
                {filter.label}
            </span>
            {children}
        </div>
    );
}
