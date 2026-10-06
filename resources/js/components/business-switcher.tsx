import { Link, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, List, Plus } from 'lucide-react';
import { update as switchBusiness } from '@/actions/App/Http/Controllers/CurrentBusinessController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { create, index } from '@/routes/businesses';

/**
 * The sidebar's first stop: which business the app is working on, the others
 * it can switch to, and the way to set up another or see them all.
 */
export function BusinessSwitcher() {
    const { businessChooser: businesses } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const cleanup = useMobileNavigation();
    const current = businesses.current;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                            tooltip={{
                                children: current?.name ?? 'Choose a business',
                            }}
                            data-test="business-switcher"
                        >
                            <div className="flex aspect-square size-8 items-center justify-center border border-ribbon-pale/40 bg-ink-lift font-mono text-xs text-ribbon-pale">
                                {current ? (
                                    current.name.charAt(0).toUpperCase()
                                ) : (
                                    <Building2 aria-hidden className="size-4" />
                                )}
                            </div>
                            <div className="grid flex-1 text-left leading-tight">
                                <span className="label-micro text-sidebar-foreground/60">
                                    Business
                                </span>
                                <span className="truncate text-sm font-medium">
                                    {current?.name ?? 'None yet'}
                                </span>
                            </div>
                            <ChevronsUpDown
                                aria-hidden
                                className="ml-auto size-4"
                            />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                        align="start"
                        side={
                            !isMobile && state === 'collapsed'
                                ? 'right'
                                : 'bottom'
                        }
                    >
                        {businesses.all.length > 0 && (
                            <>
                                <DropdownMenuLabel className="label-micro text-muted-foreground">
                                    Businesses
                                </DropdownMenuLabel>
                                <DropdownMenuGroup>
                                    {businesses.all.map((business) => (
                                        <DropdownMenuItem
                                            key={business.id}
                                            asChild
                                        >
                                            <Link
                                                href={switchBusiness()}
                                                data={{
                                                    business_id: business.id,
                                                }}
                                                as="button"
                                                className="w-full cursor-pointer"
                                                onClick={cleanup}
                                            >
                                                <span className="truncate">
                                                    {business.name}
                                                </span>
                                                {business.id ===
                                                    current?.id && (
                                                    <Check
                                                        aria-label="Current business"
                                                        className="ml-auto"
                                                    />
                                                )}
                                            </Link>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                            </>
                        )}

                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link
                                    href={create()}
                                    className="w-full cursor-pointer"
                                    onClick={cleanup}
                                >
                                    <Plus aria-hidden />
                                    New business
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link
                                    href={index()}
                                    className="w-full cursor-pointer"
                                    onClick={cleanup}
                                    prefetch
                                >
                                    <List aria-hidden />
                                    All businesses
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
