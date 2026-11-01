import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import { useState } from "react";
import { croatianPlural } from "@/utils/slipMailing";
import CashFlowWidget from "@/Components/Dashboard/CashFlowWidget";
import RevenueTrendChart from "@/Components/Dashboard/RevenueTrendChart";
import DashboardQuickActions from "@/Components/Dashboard/DashboardQuickActions";
import MailingStatusCard from "@/Components/Dashboard/MailingStatusCard";
import GroupCards from "@/Components/Dashboard/GroupCards";

function reminderNoticeText(count, days) {
    const noun = croatianPlural(
        count,
        "račun je dospio",
        "računa su dospjela",
        "računa je dospjelo",
    );
    const unpaid = croatianPlural(count, "nije podmiren", "nisu podmirena", "nije podmireno");

    return `${count} ${noun} prije više od ${days} dana i još ${unpaid}. Provjerite izvod, pa odaberite kome šaljemo podsjetnik.`;
}

export default function Dashboard({ revenue, groups, reminderNotice }) {
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
                {reminderNotice?.count > 0 && (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <p className="text-sm text-amber-950 dark:text-amber-100">
                            {reminderNoticeText(reminderNotice.count, reminderNotice.graceDays ?? 10)}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Link
                                href={route("invoices.reminders")}
                                className="inline-flex items-center rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700"
                            >
                                Pregledaj popis
                            </Link>
                            <Link
                                href={route("invoices.importMbanking.index")}
                                className="inline-flex items-center rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-500/40 dark:bg-transparent dark:text-amber-100 dark:hover:bg-amber-500/10"
                            >
                                Uvoz izvoda
                            </Link>
                        </div>
                    </div>
                )}
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
