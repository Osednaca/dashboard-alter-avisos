import type { ReactNode } from 'react';
import { BusinessSidebar } from '@/Components/app/BusinessSidebar';
import { BusinessTopNavigation } from '@/Components/app/BusinessTopNavigation';
import { DashboardLayout } from '@/Layouts/DashboardLayout';

export function BusinessLayout({
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
            storageKey="signage:business-sidebar:v1"
            searchCommand
            searchCommandProps={{ endpoint: '/business/search', placeholder: 'Buscar contenido, pantallas, programación…' }}
            sidebar={({ collapsed, mobile }) =>
                mobile ? (
                    <BusinessSidebar collapsed={false} className="!flex w-64 lg:!hidden" />
                ) : (
                    <BusinessSidebar collapsed={collapsed} />
                )
            }
            topNavigation={(opts) => <BusinessTopNavigation {...opts} />}
        />
    );
}
