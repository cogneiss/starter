import type { QueryParameters } from '@/components/data-table-views';
import type { ResourceQuery } from '@/types/generated';

/**
 * The query as URL parameters, with anything the server would have defaulted to
 * left out. A first visit has a clean address bar; every deviation from the
 * default is visible in it.
 */
export function parameters(query: ResourceQuery): QueryParameters {
    const parameters: QueryParameters = {};

    if (query.q !== '') {
        parameters.q = query.q;
    }

    if (query.sort !== null) {
        parameters.sort = query.sort;
        parameters.dir = query.dir;
    }

    if (query.page > 1) {
        parameters.page = query.page;
    }

    const filters: Record<string, string | string[] | Record<string, string>> =
        {};

    for (const [key, value] of Object.entries(query.filters)) {
        const serialized = serialize(value);

        if (serialized !== null) {
            filters[key] = serialized;
        }
    }

    if (Object.keys(filters).length > 0) {
        parameters.f = filters;
    }

    return parameters;
}

/**
 * One filter value as the server writes it back out: booleans as `1`/`0`, bounds
 * as strings with the empty ones left out. The two sides serialize the same way
 * on purpose — a URL the table builds must parse back into the query it drew.
 */
function serialize(
    value: ResourceQuery['filters'][string],
): string | string[] | Record<string, string> | null {
    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (Array.isArray(value)) {
        return value.length === 0 ? null : value;
    }

    const bounds: Record<string, string> = {};

    for (const [bound, edge] of Object.entries(value)) {
        if (edge !== '') {
            bounds[bound] = String(edge);
        }
    }

    return Object.keys(bounds).length === 0 ? null : bounds;
}
