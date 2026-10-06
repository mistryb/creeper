import type { Auth } from '@/types/auth';
import type { BusinessChoices } from '@/types/creeper';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            businessChooser: BusinessChoices;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
