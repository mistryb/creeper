import { Link } from '@inertiajs/react';
import { CircleMinus, CirclePlus, Repeat } from 'lucide-react';
import { EmptyLine } from '@/components/ds';
import { formatRelative } from '@/lib/format';
import { show } from '@/routes/watched-pages';
import type { CreepChange } from '@/types';

/**
 * The log of what moved. Each kind of change gets an icon and a label as well
 * as a ribbon colour: something new is green, something gone is red, a value
 * that moved wants attention.
 */
export function ChangeList({
    changes,
    showPage = false,
    emptyMessage = 'Nothing has changed yet',
}: {
    changes: CreepChange[];
    showPage?: boolean;
    emptyMessage?: string;
}) {
    if (changes.length === 0) {
        return <EmptyLine>{emptyMessage}</EmptyLine>;
    }

    return (
        <ul className="divide-y divide-rule">
            {changes.map((change) => (
                <li
                    key={change.id}
                    className="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                    <KindIcon kind={change.kind} label={change.kind_label} />

                    <div className="min-w-0 flex-1">
                        <p className="text-sm">{change.description}</p>
                        <p className="mt-0.5 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                            {showPage && change.watched_page && (
                                <>
                                    {change.watched_page.competitor && (
                                        <>
                                            {
                                                change.watched_page.competitor
                                                    .name
                                            }
                                            <span aria-hidden> · </span>
                                        </>
                                    )}
                                    <Link
                                        href={show(change.watched_page.id)}
                                        className="underline decoration-rule underline-offset-2 hover:text-foreground hover:decoration-ribbon"
                                    >
                                        {change.watched_page.display_name}
                                    </Link>
                                    <span aria-hidden> · </span>
                                    {change.watched_page.category_label.toLowerCase()}
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

function KindIcon({
    kind,
    label,
}: {
    kind: CreepChange['kind'];
    label: string;
}) {
    const Icon = { added: CirclePlus, removed: CircleMinus, changed: Repeat }[
        kind
    ];
    const tone = {
        added: 'text-ribbon',
        removed: 'text-ribbon-red',
        changed: 'text-ribbon-amber',
    }[kind];

    return (
        <Icon aria-label={label} className={`mt-0.5 size-4 shrink-0 ${tone}`} />
    );
}
