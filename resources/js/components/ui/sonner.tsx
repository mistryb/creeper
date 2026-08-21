import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useFlashToast } from '@/hooks/use-flash-toast';

function Toaster({ ...props }: ToasterProps) {
    useFlashToast();

    return (
        <Sonner
            theme="light"
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--color-ink)',
                    '--border-radius': '0px',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
