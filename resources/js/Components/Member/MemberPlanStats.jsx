import { Link } from "@inertiajs/react";
import { ArrowRightIcon, UsersIcon } from "@heroicons/react/24/outline";

function membersIndexHref({ workshopId, groupId, filter, billingFrequency }) {
    const params = {};

    if (filter) params.filter = filter;
    if (workshopId) params.workshop_id = workshopId;
    if (groupId) params.group_id = groupId;
    if (billingFrequency) params.billing_frequency = billingFrequency;

    return route("members.index", params);
}

export default function MemberPlanStats({
    stats,
    billingFrequency = "",
    workshopId = "",
    groupId = "",
    filter = "",
}) {
    const frequencies = stats?.frequencies || [];
    const shared = { workshopId, groupId, filter };

    const cells = [
        {
            key: "",
            label: "Ukupno članova",
            count: stats?.total || 0,
            href: membersIndexHref(shared),
        },
        ...frequencies.map((frequency) => ({
            key: frequency.key,
            label: frequency.label,
            count: frequency.count || 0,
            href: membersIndexHref({
                ...shared,
                billingFrequency: frequency.key,
            }),
        })),
    ];

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 className="mb-5 text-lg font-semibold text-gray-800 dark:text-white">
                Pregled
            </h3>
            <div className="grid grid-cols-2 overflow-hidden rounded-2xl border border-gray-200 sm:grid-cols-3 xl:grid-cols-5 dark:border-gray-800">
                {cells.map((cell) => {
                    const selected = (billingFrequency || "") === cell.key;

                    return (
                        <Link
                            key={cell.key || "total"}
                            href={cell.href}
                            className={`border-b border-gray-200 px-6 py-5 sm:border-r dark:border-gray-800 transition-colors ${
                                selected
                                    ? "bg-brand-50 dark:bg-brand-500/10"
                                    : "hover:bg-gray-50 dark:hover:bg-gray-900/50"
                            }`}
                        >
                            <div className="mb-2 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <UsersIcon className="h-5 w-5 text-brand-500" />
                                    <span className="text-sm text-gray-500 dark:text-gray-400">
                                        {cell.label}
                                    </span>
                                </div>
                                <ArrowRightIcon className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                            </div>
                            <h4 className="text-title-xs font-bold text-gray-800 sm:text-title-sm dark:text-white/90">
                                {cell.count}
                            </h4>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
