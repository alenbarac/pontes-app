import { useEffect, useRef, useState } from "react";
import { Link, usePage, usePoll } from "@inertiajs/react";
import { BellIcon } from "@heroicons/react/24/outline";
import { croatianPlural } from "@/utils/slipMailing";
import { dismissMailingNotice, readDismissedMailingIds } from "@/lib/mailingActivity";

type MailingItem = {
    id: number;
    group_name: string;
    month_label: string | null;
    sent: number;
    failed: number;
    queued: number;
    total: number;
    processed: number;
    url: string;
};

type ActivityState = {
    active?: MailingItem[];
    recent_finished?: MailingItem[];
    latest?: MailingItem | null;
};

type MailingPageProps = {
    mailingActivity?: ActivityState | null;
    mailing?: { queued?: number };
    mailings?: { data?: { queued?: number }[] };
};

function pollKeys(component: string): string[] {
    if (component === "Mailings/Show") {
        return ["mailingActivity", "mailing"];
    }

    if (component === "Mailings/Index") {
        return ["mailingActivity", "mailings"];
    }

    return ["mailingActivity"];
}

function MailingPoll({ component }: { component: string }) {
    usePoll(2000, { only: pollKeys(component) });

    return null;
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
    const page = usePage();
    const props = page.props as MailingPageProps;
    const activity = props.mailingActivity;
    const [progressOpen, setProgressOpen] = useState(false);
    const [bellOpen, setBellOpen] = useState(false);
    const [dismissedIds, setDismissedIds] = useState<number[]>(() => readDismissedMailingIds());

    const active = activity?.active ?? [];
    const pageQueued =
        (page.component === "Mailings/Show" && (props.mailing?.queued ?? 0) > 0) ||
        (page.component === "Mailings/Index" &&
            (props.mailings?.data ?? []).some((row) => (row.queued ?? 0) > 0));
    const shouldPoll = active.length > 0 || pageQueued;

    const dismissed = new Set(dismissedIds);
    const notices = (activity?.recent_finished ?? []).filter((item) => !dismissed.has(item.id));
    const processed = active.reduce((sum, item) => sum + (item.processed ?? 0), 0);
    const total = active.reduce((sum, item) => sum + (item.total ?? 0), 0);

    const dismiss = (id: number) => {
        dismissMailingNotice(id);
        setDismissedIds(readDismissedMailingIds());
    };

    return (
        <>
            {shouldPoll ? <MailingPoll component={page.component} /> : null}
            {active.length === 0 && notices.length === 0 ? null : (
                <ActivityControls
                    active={active}
                    notices={notices}
                    processed={processed}
                    total={total}
                    progressOpen={progressOpen}
                    bellOpen={bellOpen}
                    setProgressOpen={setProgressOpen}
                    setBellOpen={setBellOpen}
                    dismiss={dismiss}
                />
            )}
        </>
    );
}

function ActivityControls({
    active,
    notices,
    processed,
    total,
    progressOpen,
    bellOpen,
    setProgressOpen,
    setBellOpen,
    dismiss,
}: {
    active: MailingItem[];
    notices: MailingItem[];
    processed: number;
    total: number;
    progressOpen: boolean;
    bellOpen: boolean;
    setProgressOpen: (value: boolean | ((open: boolean) => boolean)) => void;
    setBellOpen: (value: boolean | ((open: boolean) => boolean)) => void;
    dismiss: (id: number) => void;
}) {
    const rootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onPointerDown = (event: MouseEvent) => {
            if (!rootRef.current?.contains(event.target as Node)) {
                setProgressOpen(false);
                setBellOpen(false);
            }
        };

        document.addEventListener("mousedown", onPointerDown);
        return () => document.removeEventListener("mousedown", onPointerDown);
    }, [setBellOpen, setProgressOpen]);

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
                                                onClick={() => dismiss(item.id)}
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
