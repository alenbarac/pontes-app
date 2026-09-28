export function invoiceMonthLabel(dueDate) {
    const match = String(dueDate ?? "").match(/^(\d{4})-(\d{2})/);
    if (!match) {
        return "";
    }

    return `${match[2]}/${match[1]}`;
}

export function formatSentOn(mailing) {
    if (!mailing) {
        return "";
    }

    if (mailing.sent_on) {
        return mailing.sent_on;
    }

    const match = String(mailing.sent_at ?? "").match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) {
        return "";
    }

    return `${match[3]}.${match[2]}.`;
}

export function slipMailingPresentation(mailing) {
    if (!mailing?.status) {
        return {
            status: "none",
            label: "Nije poslana",
            badge: "Nije poslana",
            color: "light",
            recipient: null,
            error: null,
            resent: false,
        };
    }

    if (mailing.status === "queued") {
        return {
            status: "queued",
            label: "U redu",
            badge: "U redu",
            color: "info",
            recipient: mailing.recipient || null,
            error: null,
            resent: false,
        };
    }

    if (mailing.status === "failed") {
        return {
            status: "failed",
            label: "Greška",
            badge: "Greška",
            color: "error",
            recipient: mailing.recipient || null,
            error: mailing.error || null,
            resent: false,
        };
    }

    const sentOn = formatSentOn(mailing);
    const dated = sentOn ? `Poslana ${sentOn}` : "Poslana";

    return {
        status: "sent",
        label: dated,
        badge: dated,
        color: "success",
        recipient: mailing.recipient || null,
        error: null,
        resent: Boolean(mailing.resent),
    };
}

export function reminderMailingPresentation(mailing) {
    if (!mailing?.status) {
        return null;
    }

    if (mailing.status === "queued") {
        return {
            label: "Opomena u redu",
            recipient: mailing.recipient || null,
            error: null,
        };
    }

    if (mailing.status === "failed") {
        return {
            label: "Opomena — greška",
            recipient: mailing.recipient || null,
            error: mailing.error || null,
        };
    }

    const sentOn = formatSentOn(mailing);

    return {
        label: sentOn ? `Opomena poslana ${sentOn}` : "Opomena poslana",
        recipient: mailing.recipient || null,
        error: null,
    };
}
