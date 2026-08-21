import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * The everyday container: a box ruled off on paper. The header is separated by
 * a printed hairline rather than by whitespace, and its title is set as a
 * stamped field name, so a page of cards reads like a filled-in form.
 *
 * For the emphatic, ink-framed version with a title bar, use `Panel`.
 */
function Card({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card"
            className={cn(
                'flex flex-col border border-rule bg-card text-card-foreground shadow-xs',
                className,
            )}
            {...props}
        />
    );
}

function CardHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-header"
            className={cn(
                'flex flex-col gap-1 border-b border-rule px-5 py-3.5',
                className,
            )}
            {...props}
        />
    );
}

function CardTitle({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-title"
            className={cn(
                'font-mono text-[0.875rem] font-semibold tracking-[0.14em] uppercase',
                className,
            )}
            {...props}
        />
    );
}

function CardDescription({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-description"
            className={cn('text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

function CardContent({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-content"
            className={cn('px-5 py-5', className)}
            {...props}
        />
    );
}

function CardFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-footer"
            className={cn(
                'flex items-center gap-3 border-t border-rule px-5 py-3.5',
                className,
            )}
            {...props}
        />
    );
}

export {
    Card,
    CardHeader,
    CardFooter,
    CardTitle,
    CardDescription,
    CardContent,
};
