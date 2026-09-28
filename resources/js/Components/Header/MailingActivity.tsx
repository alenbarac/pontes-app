import { useEffect, useRef, useState } from "react";
import { Link } from "@inertiajs/react";
import { BellIcon } from "@heroicons/react/24/outline";
import { croatianPlural } from "@/utils/slipMailing";
import {
    bootstrapMailingActivity,
    dismissMailingNotice,
    getMailingActivity,
    subscribeMailingActivity,
} from "@/lib/mailingActivity";

type MailingItem = {
    id: number;
    group_name: string;
    month_label: string | null;
    sent: number;
    failed: number;
    total: number;
    processed: number;
    url: string;
};

type ActivityState = {
    active: MailingItem[];
    recentFinished: MailingItem[];
    dismissedIds: number[];
    loaded: boolean;
};

function useActivity(): ActivityState {
    const [snapshot, setSnapshot] = useState<ActivityState>(getMailingActivity());

    useEffect(() => subscribeMailingActivity(setSnapshot), []);

    return snapshot;
}

function finishedCopy(item: MailingItem): string {
    if (item.failed === 0) {
        const noun = croatianPlural(item.sent, "uplatnica", "uplatnice", "uplatnica");
        const verb = croatianPlural(item.sent, "poslana", "poslane", "poslano");
        return `${item.sent} ${noun} uspješno ${verb}`;
    }

    const failedLabel = croatianPlural(item.failed, "nije uspjela", "nisu uspjele", "nije uspjelo");
    return `${item.sent} poslano, ${item.failed} ${failedLabel}`;
}

function contextLine(item: MailingItem): string {
    if (item.month_label) {
        return `${item.group_name} · ${item.month_label}`;
    }

    return item.group_name;
}

function ProgressBar({ processed, total }: { processed: number; total: number }) {
    const pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

    return (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
            <div className="h-full rounded-full bg-brand-500" style={{ width: `${pct}%` }} />
        </div>
    );
}

export default function MailingActivity() {
    const activity = useActivity();
    const [progressOpen, setProgressOpen] = useState(false);
    const [bellOpen, setBellOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        bootstrapMailingActivity();
    }, []);

    useEffect(() => {
        const onPointerDown = (event: MouseEvent) => {
            if (!rootRef.current?.contains(event.target as Node)) {
                setProgressOpen(false);
                setBellOpen(false);
            }
        };

        document.addEventListener("mousedown", onPointerDown);
        return () => document.removeEventListener("mousedown", onPointerDown);
    }, []);

    const active = activity.active ?? [];
    const dismissed = new Set(activity.dismissedIds ?? []);
    const notices = (activity.recentFinished ?? []).filter((item) => !dismissed.has(item.id));
    const processed = active.reduce((sum, item) => sum + (item.processed ?? 0), 0);
    const total = active.reduce((sum, item) => sum + (item.total ?? 0), 0);

    if (active.length === 0 && notices.length === 0) {
        return null;
    }

    return (
        <div ref={rootRef} className="flex items-center gap-2">
            {active.length > 0 && (
                <div className="relative">
                    <button
                        type="button"
                        onClick={() => {
                            setProgressOpen((open) => !open);
                            setBellOpen(false);
                        }}
                        className="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <span className="h-1.5 w-1.5 rounded-full bg-brand-500" />
                        Slanje u tijeku · {processed}/{total}
                    </button>
                    {progressOpen && (
                        <div className="absolute right-0 z-50 mt-2 w-80 rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                            <ul className="space-y-3">
                                {active.map((item) => (
                                    <li key={item.id}>
                                        <Link
                                            href={item.url}
                                            className="block rounded-xl px-2 py-2 hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                                            onClick={() => setProgressOpen(false)}
                                        >
                                            <p className="text-sm font-medium text-gray-800 dark:text-white">
                                                Uplatnice · {item.group_name}
                                            </p>
                                            {item.month_label && (
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    {item.month_label}
                                                </p>
                                            )}
                                            <div className="mt-2 flex items-center gap-3">
                                                <ProgressBar processed={item.processed} total={item.total} />
                                                <span className="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                                    {item.processed}/{item.total}
                                                </span>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}

            {notices.length > 0 && (
                <div className="relative">
                    <button
                        type="button"
                        aria-label="Završena slanja"
                        onClick={() => {
                            setBellOpen((open) => !open);
                            setProgressOpen(false);
                        }}
                        className="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
                    >
                        <BellIcon className="h-5 w-5" />
                        <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-brand-500" />
                    </button>
                    {bellOpen && (
                        <div className="absolute right-0 z-50 mt-2 w-80 rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                            <ul className="space-y-3">
                                {notices.map((item) => (
                                    <li
                                        key={item.id}
                                        className="rounded-xl border border-gray-100 px-3 py-3 dark:border-gray-800"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-medium text-gray-800 dark:text-white">
                                                    Slanje uplatnica završeno
                                                </p>
                                                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    {contextLine(item)}
                                                </p>
                                                <p className="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                                    {finishedCopy(item)}
                                                </p>
                                                <Link
                                                    href={item.url}
                                                    className="mt-2 inline-flex text-sm font-medium text-brand-600 hover:text-brand-700"
                                                    onClick={() => setBellOpen(false)}
                                                >
                                                    Pogledaj detalje
                                                </Link>
                                            </div>
                                            <button
                                                type="button"
                                                aria-label="Zatvori obavijest"
                                                onClick={() => dismissMailingNotice(item.id)}
                                                className="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
