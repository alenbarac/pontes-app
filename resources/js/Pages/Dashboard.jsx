import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import CashFlowWidget from "@/Components/Dashboard/CashFlowWidget";
import RevenueTrendChart from "@/Components/Dashboard/RevenueTrendChart";
import DashboardQuickActions from "@/Components/Dashboard/DashboardQuickActions";
import MailingStatusCard from "@/Components/Dashboard/MailingStatusCard";
import GroupCards from "@/Components/Dashboard/GroupCards";

export default function Dashboard({ revenue, groups }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                <CashFlowWidget revenue={revenue} />
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <RevenueTrendChart trend={revenue?.trend} />
                    </div>
                    <div className="space-y-6">
                        <DashboardQuickActions />
                        <MailingStatusCard />
                    </div>
                </div>

                <GroupCards groups={groups} />
            </div>
        </AuthenticatedLayout>
    );
}
