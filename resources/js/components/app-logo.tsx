import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-7 items-center justify-center border border-ribbon-pale/45 text-ribbon-pale">
                <AppLogoIcon className="size-4" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate label-micro text-[0.8125rem] font-semibold tracking-[0.22em]">
                    {name}
                </span>
            </div>
        </>
    );
}
