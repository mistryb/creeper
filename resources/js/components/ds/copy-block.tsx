import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import { Panel, PanelBar } from './panel';
import { Terminal } from './terminal';

/**
 * A block of text whose whole job is to leave in one piece — a prompt to hand
 * an agent, a command to paste into a shell. It is machine voice, so it is set
 * in the `Terminal`, and the copy button lives inside the frame with the text
 * rather than off in the page.
 */
export function CopyBlock({
    title,
    text,
    meta,
    action = 'Copy',
    className,
}: {
    /** What the block holds, named in the panel bar. */
    title: string;
    text: string;
    /** Right-aligned aside in the bar, e.g. a line count. */
    meta?: string;
    /** The button's label before it has been pressed. */
    action?: string;
    className?: string;
}) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === text;

    return (
        <Panel lifted={false} className={className}>
            <PanelBar title={title} meta={meta} />

            <div className="space-y-4 p-4 sm:p-5">
                <Terminal className="border-t-0 px-0 pt-0 whitespace-pre-wrap">
                    {text}
                </Terminal>

                <Button
                    type="button"
                    onClick={() => void copy(text)}
                    aria-live="polite"
                    className={cn(copied && 'bg-ribbon-lit')}
                >
                    {copied ? 'Copied' : action}
                </Button>
            </div>
        </Panel>
    );
}
