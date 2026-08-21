import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * A rubber stamp. The four tone variants — ok, warn, bad, muted — exist so
 * that every state in the app reaches for the same ribbon colour rather than
 * picking a fresh one, and so the meaning survives being read in greyscale:
 * each is a tinted ground plus a matching outline, and callers pair it with a
 * word and an icon.
 */
const badgeVariants = cva(
    'inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden border px-1.5 py-0.5 font-mono text-[0.6875rem] font-medium tracking-[0.08em] whitespace-nowrap uppercase transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:ring-offset-background aria-invalid:border-destructive [&>svg]:pointer-events-none [&>svg]:size-3',
    {
        variants: {
            variant: {
                default: 'border-ink bg-ink text-paper-lit',
                secondary: 'border-rule bg-secondary text-secondary-foreground',
                outline: 'border-rule bg-transparent text-foreground',
                destructive:
                    'border-ink bg-destructive text-destructive-foreground',
                ok: 'border-ribbon/35 bg-ribbon/10 text-ribbon',
                warn: 'border-ribbon-amber/35 bg-ribbon-amber/10 text-ribbon-amber',
                bad: 'border-ribbon-red/35 bg-ribbon-red/10 text-ribbon-red',
                muted: 'border-rule bg-transparent text-muted-foreground',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Badge({
    className,
    variant,
    asChild = false,
    ...props
}: React.ComponentProps<'span'> &
    VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'span';

    return (
        <Comp
            data-slot="badge"
            className={cn(badgeVariants({ variant }), className)}
            {...props}
        />
    );
}

export { Badge, badgeVariants };
