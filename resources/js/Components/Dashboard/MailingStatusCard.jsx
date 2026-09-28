import { Link } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { getMailingActivity, subscribeMailingActivity } from "@/lib/mailingActivity";

function lineFor(item) {
    if (!item) {
        return "";
    }

    if ((item.queued ?? 0) > 0) {
        return `${item.group_name} · ${item.processed}/${item.total}`;
    }

    if ((item.failed ?? 0) === 0) {
        return `${item.group_name} · ${item.sent}/${item.total} poslano`;
    }

    return `${item.group_name} · ${item.sent}/${item.total}`;
}

function Progress({ item }) {
    const pct = item.total > 0 ? Math.min(100, Math.round((item.processed / item.total) * 100)) : 0;

    return (
        <div>
            <div className="flex items-center justify-between gap-3 text-sm">
                <p className="font-medium text-gray-800 dark:text-white">{lineFor(item)}</p>
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    {item.processed}/{item.total}
                </span>
            </div>
            {item.month_label && (
                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{item.month_label}</p>
            )}
            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div className="h-full rounded-full bg-brand-500" style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

export default function MailingStatusCard({ snapshot }) {
    const [live, setLive] = useState(getMailingActivity());

    useEffect(() => subscribeMailingActivity(setLive), []);

    const data = live.loaded
        ? { active: live.active, latest: live.latest }
        : snapshot ?? { active: [], latest: null };

    const active = data.active ?? [];
    const latest = data.latest;

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="mb-3 flex items-center justify-between gap-3">
                <h3 className="text-sm font-semibold text-gray-800 dark:text-white">Evidencija slanja</h3>
                <Link href={route("mailings.index")} className="text-xs font-medium text-brand-600 hover:text-brand-700">
                    Otvori
                </Link>
            </div>

            {active.length > 0 ? (
                <div className="space-y-4">
                    <p className="text-xs text-gray-500 dark:text-gray-400">Slanje u tijeku</p>
                    {active.map((item) => (
                        <Link key={item.id} href={item.url} className="block">
                            <Progress item={item} />
                        </Link>
                    ))}
                </div>
            ) : latest ? (
                <Link href={latest.url} className="block text-sm font-medium text-gray-800 hover:text-brand-600 dark:text-white">
                    {lineFor(latest)}
                </Link>
            ) : (
                <p className="text-sm text-gray-500 dark:text-gray-400">Još nema slanja.</p>
            )}
        </div>
    );
}
