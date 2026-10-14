import {
    CheckCircle2,
    CircleDashed,
    LoaderCircle,
    TriangleAlert,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { RunStatus } from '@/types';

/**
 * A run's state, as a badge. Each pairs its ribbon colour with an icon and a
 * word, so state is never carried by colour alone: grey is "not yet", amber
 * is "going", green is done, red is damage.
 */
export function RunStatusBadge({
    status,
    label,
}: {
    status: RunStatus;
    label: string;
}) {
    const config = {
        queued: { variant: 'muted' as const, Icon: CircleDashed },
        running: { variant: 'warn' as const, Icon: LoaderCircle },
        succeeded: { variant: 'ok' as const, Icon: CheckCircle2 },
        failed: { variant: 'bad' as const, Icon: TriangleAlert },
    }[status];

    return (
        <Badge variant={config.variant}>
            <config.Icon
                aria-hidden
                className={status === 'running' ? 'animate-spin' : undefined}
            />
            {label}
        </Badge>
    );
}
