import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronDown } from 'lucide-react';
import { update as switchBusiness } from '@/actions/App/Http/Controllers/CurrentBusinessController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { navPill } from '@/lib/nav-pill';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/businesses';

/**
 * The company the app is working on, as one word in the header. Opening it
 * switches to another company, or reaches the company's own settings.
 */
export function CompanyMenu() {
    const { businessChooser } = usePage().props;
    const current = businessChooser.current;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    navPill,
                    'border-ribbon bg-greenbar text-ribbon uppercase outline-none hover:border-ink hover:text-ink focus-visible:border-ink data-[state=open]:border-ink data-[state=open]:text-ink',
                )}
                data-test="company-menu"
            >
                <span className="max-w-40 truncate">
                    {current?.name ?? 'No company'}
                </span>
                <ChevronDown aria-hidden className="size-3" />
            </DropdownMenuTrigger>

            <DropdownMenuContent align="center" className="min-w-56">
                {businessChooser.all.map((company) => (
                    <DropdownMenuItem key={company.id} asChild>
                        <Link
                            href={switchBusiness()}
                            data={{ business_id: company.id }}
                            as="button"
                            className="w-full cursor-pointer"
                        >
                            <span className="truncate">{company.name}</span>
                            {company.id === current?.id && (
                                <Check
                                    aria-label="Current company"
                                    className="ml-auto"
                                />
                            )}
                        </Link>
                    </DropdownMenuItem>
                ))}

                {businessChooser.all.length > 0 && <DropdownMenuSeparator />}

                {current && (
                    <DropdownMenuItem asChild>
                        <Link
                            href={show(current.id)}
                            className="w-full cursor-pointer"
                        >
                            Company settings
                        </Link>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link href={create()} className="w-full cursor-pointer">
                        New company
                    </Link>
                </DropdownMenuItem>
                {businessChooser.all.length > 1 && (
                    <DropdownMenuItem asChild>
                        <Link href={index()} className="w-full cursor-pointer">
                            All companies
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
