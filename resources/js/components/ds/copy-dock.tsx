import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

/**
 * The copy button of a `CopyBlock`, docked to the foot of the viewport and
 * centred, so text whose whole job is to leave in one piece can leave from
 * anywhere on the page without being scrolled back to. It is always there —
 * a page that exists to hand something over should never hide the handle.
 *
 * It rides on its own plate: ink frame, paper-lit ground, the framed-panel
 * offset. The dock is fixed, so it passes over the ink slabs at the foot of a
 * marketing page, where a bare ink-outlined key would lose its edges — on the
 * plate it reads on paper and on ink alike.
 *
 * The key is amber rather than the usual ribbon green because it is the one
 * thing on the page asking to be pressed, and amber is the colour that wants
 * attention. It answers in green once it has been pressed.
 */
export function CopyDock({
    text,
    label = 'Copy',
    title,
    className,
}: {
    text: string;
    /** The button's label before it has been pressed. */
    label?: string;
    /** What the dock hands over, named on the plate beside the key. */
    title?: string;
    className?: string;
}) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === text;

    return (
        <div
            className={cn(
                'fixed bottom-4 left-1/2 z-40 -translate-x-1/2 sm:bottom-8',
                className,
            )}
        >
            <div className="flex items-center gap-3 border border-ink bg-paper-lit p-2 shadow-stamp sm:gap-4 sm:pl-4">
                {title && (
                    <span className="hidden items-center gap-2 label-micro text-ink-soft sm:flex">
                        <i
                            aria-hidden="true"
                            className="size-2 rounded-full bg-ribbon-amber"
                        />
                        {title}
                    </span>
                )}

                <Button
                    type="button"
                    size="lg"
                    onClick={() => void copy(text)}
                    aria-live="polite"
                    className={cn(
                        copied
                            ? 'bg-ribbon-lit hover:bg-ribbon-lit'
                            : 'bg-ribbon-amber hover:bg-ribbon-amber-lit',
                    )}
                >
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Copied' : label}
                </Button>
            </div>
        </div>
    );
}
