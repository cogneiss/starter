import type { ReactNode } from 'react';
import type { ResourceList } from '@/types/generated';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

/** A server list whose rows the page knows the shape of. */
export type TypedResourceList<TRow> = Omit<ResourceList, 'rows'> & {
    rows: TRow[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
