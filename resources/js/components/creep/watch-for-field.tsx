import { useState } from 'react';
import { Field } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { WATCH_PRESETS } from '@/lib/watch-presets';
import type { WatchPreset } from '@/lib/watch-presets';

/**
 * "What should Creeper watch for?": the user's own words, with a few presets
 * to start from. Picking a preset fills the box; the words stay theirs to
 * change. The text is submitted as `watch_for`.
 */
export function WatchForField({
    defaultValue = '',
    error,
    onPreset,
}: {
    defaultValue?: string;
    error?: string;
    /** Told when a preset is picked, e.g. to suggest a URL for it. */
    onPreset?: (preset: WatchPreset) => void;
}) {
    const [watchFor, setWatchFor] = useState(defaultValue);

    return (
        <Field
            label="What should Creeper watch for?"
            htmlFor="watch_for"
            error={error}
            hint="Describe it the way you would to a colleague. Creeper reads the page for exactly this, and tells you when any of it changes."
        >
            <div
                role="group"
                aria-label="Start from a preset"
                className="flex flex-wrap gap-2"
            >
                {WATCH_PRESETS.map((preset) => (
                    <Button
                        key={preset.label}
                        type="button"
                        size="sm"
                        variant={
                            watchFor === preset.watchFor ? 'default' : 'outline'
                        }
                        aria-pressed={watchFor === preset.watchFor}
                        onClick={() => {
                            setWatchFor(preset.watchFor);
                            onPreset?.(preset);
                        }}
                    >
                        {preset.label}
                    </Button>
                ))}
            </div>

            <Textarea
                id="watch_for"
                name="watch_for"
                required
                rows={4}
                maxLength={2000}
                value={watchFor}
                onChange={(event) => setWatchFor(event.target.value)}
                aria-invalid={error ? true : undefined}
                placeholder="e.g. Each plan's name and price, and whether there is still a free tier."
            />
        </Field>
    );
}
