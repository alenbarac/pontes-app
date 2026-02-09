import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import DashboardMetrics from "@/Components/Dashboard/DashboardMetrics";
import RecentInvoicesTable from "@/Components/Dashboard/RecentInvoicesTable";
import RecentMembersTable from "@/Components/Dashboard/RecentMembersTable";

export default function Dashboard({ stats, recent_invoices, recent_members }) {
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
                {/* Metrics Cards */}
                <DashboardMetrics stats={stats} />

                {/* Recent Data Tables */}
                <div className="space-y-6">
                    <RecentInvoicesTable invoices={recent_invoices} />
                    <RecentMembersTable members={recent_members} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
