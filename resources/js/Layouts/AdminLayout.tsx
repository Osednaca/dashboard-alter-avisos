import type { ReactNode } from 'react';
import { AppSidebar } from '@/Components/app/AppSidebar';
import { TopNavigation } from '@/Components/app/TopNavigation';
import { DashboardLayout } from '@/Layouts/DashboardLayout';

export function AdminLayout({
    children,
    header,
    contentClassName,
}: {
    children: ReactNode;
    header?: ReactNode;
    contentClassName?: string;
}) {
    return (
        <DashboardLayout
            header={header}
            contentClassName={contentClassName}
            children={children}
            searchCommand
            storageKey="signage:sidebar:v1"
            sidebar={({ collapsed, mobile }) =>
                mobile ? (
                    <AppSidebar collapsed={false} className="!flex w-64 lg:!hidden" />
                ) : (
                    <AppSidebar collapsed={collapsed} />
                )
            }
            topNavigation={(opts) => <TopNavigation {...opts} />}
        />
    );
}
