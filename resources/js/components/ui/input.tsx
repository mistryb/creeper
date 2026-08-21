import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * A keyed field: brighter than the paper around it, outlined in ink, and it
 * thickens rather than glows when focused — the way a filled box on a form is
 * pressed into the page.
 */
function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'flex h-9 w-full min-w-0 border border-input bg-white px-3 py-1 text-base transition-shadow outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground/70 disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-secondary disabled:opacity-60 md:text-sm',
                'focus-visible:shadow-[inset_0_0_0_1px_var(--color-ink)]',
                'aria-invalid:border-destructive aria-invalid:focus-visible:shadow-[inset_0_0_0_1px_var(--color-ribbon-red)]',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
