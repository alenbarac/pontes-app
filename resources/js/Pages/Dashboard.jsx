import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import RevenueInsights from "@/Components/Dashboard/RevenueInsights";
import RevenueTrendChart from "@/Components/Dashboard/RevenueTrendChart";
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
                {/* Revenue & Invoice Insights */}
                <RevenueInsights revenue={revenue} />
                <RevenueTrendChart trend={revenue?.trend} />

                {/* Groups Cards */}
                <GroupCards groups={groups} />
            </div>
        </AuthenticatedLayout>
    );
}
