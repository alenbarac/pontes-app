import { router } from "@inertiajs/react";

const DISMISS_KEY = "pontes.dismissed-mailing-ids";

export function readDismissedMailingIds() {
    if (typeof window === "undefined") {
        return [];
    }

    try {
        const parsed = JSON.parse(window.localStorage.getItem(DISMISS_KEY) || "[]");
        return Array.isArray(parsed) ? parsed.map((id) => Number(id)).filter((id) => id > 0) : [];
    } catch {
        return [];
    }
}

export function dismissMailingNotice(id) {
    const ids = readDismissedMailingIds();
    if (!ids.includes(id)) {
        ids.push(id);
        window.localStorage.setItem(DISMISS_KEY, JSON.stringify(ids));
    }
}

export function dismissMailingNotices(ids) {
    const current = new Set(readDismissedMailingIds());
    ids.forEach((id) => current.add(id));
    window.localStorage.setItem(DISMISS_KEY, JSON.stringify([...current]));
}

export function notifyMailingStarted() {
    router.reload({ only: ["mailingActivity"] });
}
