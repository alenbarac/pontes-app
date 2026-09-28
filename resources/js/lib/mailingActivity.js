import axios from "axios";

const DISMISS_KEY = "pontes.dismissed-mailing-ids";

const listeners = new Set();

let state = {
    active: [],
    recentFinished: [],
    latest: null,
    loaded: false,
    dismissedIds: [],
};

let timer = null;
let bootstrapped = false;

function readDismissed() {
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

function emit() {
    state = { ...state, dismissedIds: readDismissed() };
    listeners.forEach((listener) => listener(state));
}

export function getMailingActivity() {
    return state;
}

export function subscribeMailingActivity(listener) {
    listeners.add(listener);
    listener(state);
    return () => listeners.delete(listener);
}

async function refresh() {
    const { data } = await axios.get(route("mailings.activity"), {
        headers: { Accept: "application/json" },
    });

    state = {
        ...state,
        active: data.active ?? [],
        recentFinished: data.recent_finished ?? [],
        latest: data.latest ?? null,
        loaded: true,
        dismissedIds: readDismissed(),
    };
    listeners.forEach((listener) => listener(state));
}

function startPolling() {
    if (timer !== null || typeof window === "undefined") {
        return;
    }

    timer = window.setInterval(() => {
        refresh()
            .then(() => {
                if (state.active.length === 0) {
                    stopPolling();
                }
            })
            .catch(() => {
                stopPolling();
            });
    }, 2000);
}

function stopPolling() {
    if (timer === null || typeof window === "undefined") {
        return;
    }

    window.clearInterval(timer);
    timer = null;
}

export function bootstrapMailingActivity() {
    if (bootstrapped) {
        return;
    }

    bootstrapped = true;
    refresh()
        .then(() => {
            if (state.active.length > 0) {
                startPolling();
            }
        })
        .catch(() => {});
}

export function notifyMailingStarted() {
    refresh()
        .then(() => startPolling())
        .catch(() => {});
}

export function dismissMailingNotice(id) {
    const ids = readDismissed();
    if (!ids.includes(id)) {
        ids.push(id);
        window.localStorage.setItem(DISMISS_KEY, JSON.stringify(ids));
    }
    emit();
}

export function dismissMailingNotices(ids) {
    const current = new Set(readDismissed());
    ids.forEach((id) => current.add(id));
    window.localStorage.setItem(DISMISS_KEY, JSON.stringify([...current]));
    emit();
}
