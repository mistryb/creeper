import { Link } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Repeat } from 'lucide-react';
import { EmptyLine } from '@/components/ds';
import { formatRelative } from '@/lib/format';
import { show } from '@/routes/creep-targets';
import type { CreepChange } from '@/types';

/**
 * The log of what moved. Direction gets an arrow and a label as well as a
 * ribbon colour: down is the good news for a price watcher, so it reads green.
 */
export function ChangeList({
    changes,
    showTarget = false,
}: {
    changes: CreepChange[];
    showTarget?: boolean;
}) {
    if (changes.length === 0) {
        return <EmptyLine>Nothing has changed yet</EmptyLine>;
    }

    return (
        <ul className="divide-y divide-rule">
            {changes.map((change) => (
                <li
                    key={change.id}
                    className="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                    <DirectionIcon direction={change.direction} />

                    <div className="min-w-0 flex-1">
                        <p className="text-sm">{change.description}</p>
                        <p className="mt-0.5 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                            {showTarget && change.target && (
                                <>
                                    <Link
                                        href={show(change.target.id)}
                                        className="underline decoration-rule underline-offset-2 hover:text-foreground hover:decoration-ribbon"
                                    >
                                        {change.target.display_name}
                                    </Link>
                                    <span aria-hidden> · </span>
                                </>
                            )}
                            {formatRelative(change.detected_at)}
                        </p>
                    </div>
                </li>
            ))}
        </ul>
    );
}

function DirectionIcon({ direction }: { direction: CreepChange['direction'] }) {
    if (direction === 'down') {
        return (
            <ArrowDownRight
                aria-label="Decreased"
                className="mt-0.5 size-4 shrink-0 text-ribbon"
            />
        );
    }

    if (direction === 'up') {
        return (
            <ArrowUpRight
                aria-label="Increased"
                className="mt-0.5 size-4 shrink-0 text-ribbon-red"
            />
        );
    }

    return (
        <Repeat
            aria-label="Changed"
            className="mt-0.5 size-4 shrink-0 text-ribbon-amber"
        />
    );
}
