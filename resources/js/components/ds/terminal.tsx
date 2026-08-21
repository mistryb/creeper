import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * A block of machine output: mono, generously leaded, numbers aligned. This is
 * where Creeper speaks in its own voice — a run's log, an example, a payload —
 * as opposed to the app speaking about it.
 *
 * It is a `<pre>`, so the caller controls the line breaks and the leading
 * spaces line up. Give it an `aria-label` when the content is illustrative.
 */
export function Terminal({
    children,
    className,
    caret = false,
    ...props
}: React.ComponentProps<'pre'> & {
    /** The blinking block cursor, for output still being written. */
    caret?: boolean;
}) {
    return (
        <pre
            className={cn(
                'overflow-x-auto border-t border-dashed border-rule px-4 pt-4 font-mono text-[0.8125rem] leading-[1.9] text-ink-soft tabular-nums',
                className,
            )}
            {...props}
        >
            {children}
            {caret && (
                <span
                    aria-hidden="true"
                    className="caret ml-0.5 inline-block h-[1.05em] w-[0.55em] bg-ribbon [vertical-align:-0.2em]"
                />
            )}
        </pre>
    );
}

/** The `>` prompt, so a command line is never typed by hand. */
export function Prompt({ children }: { children?: ReactNode }) {
    return (
        <>
            <span aria-hidden="true" className="text-ribbon">
                &gt;
            </span>{' '}
            {children}
        </>
    );
}

/** A value Creeper printed: pulled up out of the soft ink of the surround. */
export function Emitted({ children }: { children: ReactNode }) {
    return <span className="font-medium text-ink">{children}</span>;
}

/** A field that moved, marked in amber the way a change is flagged. */
export function Changed({ children }: { children: ReactNode }) {
    return <span className="font-medium text-ribbon-amber">{children}</span>;
}

/** Something that went fine. */
export function Ok({ children }: { children: ReactNode }) {
    return <span className="text-ribbon">{children}</span>;
}
