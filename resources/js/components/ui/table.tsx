import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * Green-bar paper: alternating rows in a pale green, the way continuous
 * stationery was banded so the eye could hold a line across the page. This is
 * the design system's signature, and the reason tabular data in this app
 * should always be a real table rather than a list of rows.
 *
 * The wrapper scrolls on its own so a wide table never pushes the page
 * sideways.
 */
function Table({ className, ...props }: React.ComponentProps<'table'>) {
    return (
        <div
            data-slot="table-container"
            className="w-full overflow-x-auto border border-rule bg-card shadow-xs"
        >
            <table
                data-slot="table"
                className={cn('w-full caption-bottom text-sm', className)}
                {...props}
            />
        </div>
    );
}

function TableHeader({ className, ...props }: React.ComponentProps<'thead'>) {
    return (
        <thead
            data-slot="table-header"
            className={cn('border-b border-ink bg-greenbar', className)}
            {...props}
        />
    );
}

function TableBody({ className, ...props }: React.ComponentProps<'tbody'>) {
    return (
        <tbody
            data-slot="table-body"
            className={cn('[&>tr:nth-child(even)]:bg-greenbar/55', className)}
            {...props}
        />
    );
}

function TableFooter({ className, ...props }: React.ComponentProps<'tfoot'>) {
    return (
        <tfoot
            data-slot="table-footer"
            className={cn(
                'border-t border-ink bg-greenbar font-medium',
                className,
            )}
            {...props}
        />
    );
}

function TableRow({ className, ...props }: React.ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            className={cn(
                'transition-colors hover:bg-ribbon/[0.07] data-[state=selected]:bg-ribbon/10',
                className,
            )}
            {...props}
        />
    );
}

function TableHead({ className, ...props }: React.ComponentProps<'th'>) {
    return (
        <th
            data-slot="table-head"
            className={cn(
                'label-micro h-9 px-4 text-left align-middle whitespace-nowrap text-ribbon [&:has([role=checkbox])]:pr-0',
                className,
            )}
            {...props}
        />
    );
}

function TableCell({ className, ...props }: React.ComponentProps<'td'>) {
    return (
        <td
            data-slot="table-cell"
            className={cn(
                'px-4 py-3 align-middle [&:has([role=checkbox])]:pr-0',
                className,
            )}
            {...props}
        />
    );
}

function TableCaption({
    className,
    ...props
}: React.ComponentProps<'caption'>) {
    return (
        <caption
            data-slot="table-caption"
            className={cn('mt-3 text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

export {
    Table,
    TableHeader,
    TableBody,
    TableFooter,
    TableHead,
    TableRow,
    TableCell,
    TableCaption,
};
