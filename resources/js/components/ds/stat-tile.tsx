import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

const tones = {
    default: 'text-ink',
    ribbon: 'text-ribbon',
    warn: 'text-ribbon-amber',
    bad: 'text-ribbon-red',
} as const;

/**
 * A counter read off a machine: the field name stamped small, the number set
 * large in dot-matrix. The tone tints the number only — the label stays quiet
 * so a row of tiles scans as one instrument panel, and a tinted number is
 * always accompanied by a word that says the same thing.
 */
export function StatTile({
    label,
    value,
    note,
    tone = 'default',
    className,
}: {
    label: ReactNode;
    value: ReactNode;
    /** A line under the number: a delta, a unit, a timestamp. */
    note?: ReactNode;
    tone?: keyof typeof tones;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'border border-rule bg-card px-4 py-3.5 shadow-xs',
                className,
            )}
        >
            <p className="label-micro text-muted-foreground">{label}</p>
            <p className={cn('mt-2 numeral-dot text-4xl', tones[tone])}>
                {value}
            </p>
            {note && (
                <p className="mt-2 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                    {note}
                </p>
            )}
        </div>
    );
}

/**
 * Key and value with a dotted leader between them, as printed on a receipt.
 * The leader does the aligning, so the pair survives any width.
 *
 * Inside a `<dl>`, pass `labelAs="dt"` and `valueAs="dd"`: the wrapper is a
 * `<div>`, which a description list allows, and the pair then carries its
 * meaning to a screen reader as well as to the eye.
 */
export function ReceiptRow({
    label,
    value,
    labelAs: LabelTag = 'span',
    valueAs: ValueTag = 'span',
    className,
}: {
    label: ReactNode;
    value: ReactNode;
    labelAs?: 'span' | 'dt';
    valueAs?: 'span' | 'dd';
    className?: string;
}) {
    return (
        <div className={cn('flex items-baseline gap-1.5', className)}>
            <LabelTag className="shrink-0">{label}</LabelTag>
            <span
                aria-hidden="true"
                className="flex-1 -translate-y-1 border-b border-dotted border-rule"
            />
            <ValueTag className="min-w-0 shrink-0 truncate text-right text-ink-soft">
                {value}
            </ValueTag>
        </div>
    );
}
