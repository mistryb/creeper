import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * A notice pasted onto the page: square, outlined, tinted with the ribbon
 * colour that matches what it is saying. The title is stamped in mono so an
 * alert is distinguishable from body copy at a glance.
 */
const alertVariants = cva(
    'relative grid w-full grid-cols-[0_1fr] items-start gap-y-1 border px-4 py-3 text-sm has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current',
    {
        variants: {
            variant: {
                default: 'border-rule bg-card text-foreground [&>svg]:text-ribbon',
                destructive:
                    'border-ribbon-red/35 bg-ribbon-red/8 text-ribbon-red',
                warning:
                    'border-ribbon-amber/35 bg-ribbon-amber/8 text-ribbon-amber',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Alert({
    className,
    variant,
    ...props
}: React.ComponentProps<'div'> & VariantProps<typeof alertVariants>) {
    return (
        <div
            data-slot="alert"
            role="alert"
            className={cn(alertVariants({ variant }), className)}
            {...props}
        />
    );
}

function AlertTitle({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-title"
            className={cn(
                'label-mono col-start-2 min-h-4 uppercase',
                className,
            )}
            {...props}
        />
    );
}

function AlertDescription({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-description"
            className={cn(
                'col-start-2 grid justify-items-start gap-1 text-sm opacity-90 [&_p]:leading-relaxed',
                className,
            )}
            {...props}
        />
    );
}

export { Alert, AlertTitle, AlertDescription };
