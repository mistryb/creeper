import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * A key on a machine: square, outlined in ink, and it moves a pixel when you
 * press it. The label is always mono, uppercase and tracked, because a button
 * is a printed instruction rather than a sentence.
 */
const buttonVariants = cva(
    "inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 border font-mono text-xs font-semibold tracking-[0.14em] whitespace-nowrap uppercase transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:translate-y-px disabled:pointer-events-none disabled:opacity-45 aria-invalid:border-destructive [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-3.5",
    {
        variants: {
            variant: {
                default:
                    'border-ink bg-primary text-primary-foreground hover:bg-ribbon-lit',
                destructive:
                    'border-ink bg-destructive text-destructive-foreground hover:bg-ribbon-red-lit',
                secondary:
                    'border-ink bg-card text-foreground shadow-xs hover:bg-secondary',
                outline:
                    'border-rule bg-transparent text-foreground hover:border-ink hover:bg-secondary',
                ghost: 'border-transparent text-muted-foreground hover:bg-secondary hover:text-foreground',
                link: 'h-auto border-transparent p-0 tracking-[0.08em] text-primary underline decoration-rule decoration-1 underline-offset-4 hover:decoration-primary',
            },
            size: {
                default: 'h-9 px-4',
                sm: 'h-8 px-3 text-[0.6875rem]',
                lg: 'h-11 px-6',
                icon: 'size-9 [&_svg:not([class*="size-"])]:size-4',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    }) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}

export { Button, buttonVariants };
