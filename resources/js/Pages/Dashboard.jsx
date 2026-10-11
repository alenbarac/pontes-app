import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { useState } from "react";
import CashFlowWidget from "@/Components/Dashboard/CashFlowWidget";
import RevenueTrendChart from "@/Components/Dashboard/RevenueTrendChart";
import DashboardQuickActions from "@/Components/Dashboard/DashboardQuickActions";
import MailingStatusCard from "@/Components/Dashboard/MailingStatusCard";
import GroupCards from "@/Components/Dashboard/GroupCards";

export default function Dashboard({ revenue, groups }) {
    const months = revenue?.months || [];
    const [monthKey, setMonthKey] = useState(revenue?.current_month_key || "");
    const selectedKey = months.some((month) => month.key === monthKey)
        ? monthKey
        : (revenue?.current_month_key || months.at(-1)?.key || "");

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
                <DashboardQuickActions />
                <CashFlowWidget
                    months={months}
                    monthKey={selectedKey}
                    onMonthChange={setMonthKey}
                />
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <RevenueTrendChart
                        months={months}
                        monthKey={selectedKey}
                        onMonthChange={setMonthKey}
                    />
                    <div className="xl:relative">
                        <div className="xl:absolute xl:inset-0">
                            <MailingStatusCard />
                        </div>
                    </div>
                </div>

                <GroupCards groups={groups} />
            </div>
        </AuthenticatedLayout>
    );
}
