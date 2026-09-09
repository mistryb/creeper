import { Form } from '@inertiajs/react';
import { Pause, Play } from 'lucide-react';
import CreepTargetPauseController from '@/actions/App/Http/Controllers/CreepTargetPauseController';
import { Button } from '@/components/ui/button';
import type { CreepTarget } from '@/types';

/**
 * Pausing is the alternative to deleting: the schedule stops, everything
 * already found stays. One control covers both directions, because a paused
 * target and a parked one are both resumed the same way.
 */
export function PauseButton({
    target,
    size = 'default',
    variant = 'secondary',
    compact = false,
}: {
    target: CreepTarget;
    size?: 'default' | 'sm' | 'icon';
    variant?: 'secondary' | 'outline' | 'ghost';
    /** Icon only, for table rows where the label would crowd the row. */
    compact?: boolean;
}) {
    const isRunning = target.status === 'active';
    const label = isRunning ? 'Pause creeping' : 'Resume creeping';
    const Icon = isRunning ? Pause : Play;

    const action = isRunning
        ? CreepTargetPauseController.store.form(target.id)
        : CreepTargetPauseController.destroy.form(target.id);

    return (
        <Form {...action} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant={variant}
                    size={compact ? 'icon' : size}
                    disabled={processing}
                    title={compact ? label : undefined}
                >
                    <Icon aria-hidden />
                    <span className={compact ? 'sr-only' : undefined}>
                        {label}
                    </span>
                </Button>
            )}
        </Form>
    );
}
