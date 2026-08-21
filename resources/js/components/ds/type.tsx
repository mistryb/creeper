import type { ElementType, ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * The overline: a machine-printed field name above a heading, or a run of them
 * separated by slashes. It carries no information the heading does not — it
 * sets the register — so it never holds the only copy of anything.
 */
export function Eyebrow({
    children,
    className,
    tone = 'ribbon',
}: {
    children: ReactNode;
    className?: string;
    tone?: 'ribbon' | 'muted' | 'pale';
}) {
    return (
        <p
            className={cn(
                'label-micro',
                {
                    ribbon: 'text-ribbon',
                    muted: 'text-muted-foreground',
                    /* On an ink surface. */
                    pale: 'text-ribbon-pale',
                }[tone],
                className,
            )}
        >
            {children}
        </p>
    );
}

const displaySizes = {
    /* The landing hero, and nothing else. */
    hero: 'text-[clamp(2.6rem,9.5vw,5.5rem)]',
    /* A page title. */
    lg: 'text-[clamp(1.8rem,5vw,2.8rem)] leading-tight',
    /* A section heading. */
    md: 'text-[clamp(1.4rem,4vw,2.1rem)] leading-tight',
    /* An in-page heading, where a section heading would shout. */
    sm: 'text-lg leading-tight',
} as const;

/**
 * A dot-matrix headline. Doto is a display face: it is legible at size and
 * mushy below about 18px, so the scale stops there and prose never uses it.
 */
export function Display({
    children,
    className,
    size = 'md',
    as: Tag = 'h2',
}: {
    children: ReactNode;
    className?: string;
    size?: keyof typeof displaySizes;
    as?: ElementType;
}) {
    return (
        <Tag className={cn('display-dot', displaySizes[size], className)}>
            {children}
        </Tag>
    );
}

/**
 * The heading block every page and section opens with: a dot-matrix title, an
 * optional stamped note, an optional sentence of prose, and the actions that
 * belong to the section. Note and description do different jobs — a `note` is
 * a short machine-printed aside ("three steps, then it is out of your hands"),
 * a `description` is a sentence addressed to the reader — so a heading rarely
 * wants both.
 */
export function SectionHeading({
    title,
    note,
    description,
    actions,
    size = 'md',
    as = 'h1',
    className,
}: {
    title: ReactNode;
    note?: ReactNode;
    description?: ReactNode;
    /** Buttons belonging to the section, right-aligned on wide screens. */
    actions?: ReactNode;
    size?: keyof typeof displaySizes;
    as?: ElementType;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-baseline justify-between gap-x-6 gap-y-3',
                className,
            )}
        >
            <div className="min-w-0">
                <Display size={size} as={as}>
                    {title}
                </Display>
                {note && <Eyebrow className="mt-2.5">{note}</Eyebrow>}
                {description && (
                    <p className="mt-2 max-w-prose text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap gap-2 self-center">
                    {actions}
                </div>
            )}
        </div>
    );
}
