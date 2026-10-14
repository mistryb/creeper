import type { ElementType, ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * The public pages' headings. Set in the sans, large and tightly tracked,
 * rather than in the app's dot-matrix display face — the marketing pages are
 * deliberately quieter than the product.
 */
const sizes = {
    /* The one headline at the top of a page. */
    hero: 'text-[clamp(2.5rem,8vw,4.75rem)] leading-[1.02] tracking-[-0.035em]',
    /* A section further down. */
    section:
        'text-[clamp(1.75rem,4.5vw,2.5rem)] leading-[1.08] tracking-[-0.03em]',
} as const;

export function MarketingHeading({
    children,
    size = 'section',
    as: Tag = size === 'hero' ? 'h1' : 'h2',
    className,
}: {
    children: ReactNode;
    size?: keyof typeof sizes;
    as?: ElementType;
    className?: string;
}) {
    return (
        <Tag
            className={cn('font-semibold text-balance', sizes[size], className)}
        >
            {children}
        </Tag>
    );
}

/**
 * One numbered step in a list of them: the number stamped in mono, a short
 * heading, and a sentence or two. Lives in an `<ol>` with a bottom rule, so
 * every step sits between two hairlines.
 */
export function MarketingStep({
    step,
    heading,
    children,
}: {
    step: string;
    heading: string;
    children: ReactNode;
}) {
    return (
        <li className="grid grid-cols-[2.5rem_1fr] gap-3 border-t border-rule py-6">
            <span className="pt-0.5 label-mono text-ribbon">{step}</span>
            <div>
                <h3 className="mb-1 font-medium text-ink">{heading}</h3>
                <p className="text-[0.9375rem] text-ink-soft">{children}</p>
            </div>
        </li>
    );
}
