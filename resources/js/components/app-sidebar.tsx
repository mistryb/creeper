import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, ScanSearch, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { BusinessSwitcher } from '@/components/business-switcher';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as businessAnalysis } from '@/routes/businesses/analysis';
import { index as competitors } from '@/routes/businesses/competitors';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { current } = usePage().props.businessChooser;

    // Everything below the dashboard is about the business picked in the
    // chooser above, so it only appears once there is a business to be about.
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(current
            ? [
                  {
                      title: 'Analysis',
                      href: businessAnalysis(current.id),
                      icon: ScanSearch,
                  },
                  {
                      title: 'Competitors',
                      href: competitors(current.id),
                      icon: Users,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                <BusinessSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
