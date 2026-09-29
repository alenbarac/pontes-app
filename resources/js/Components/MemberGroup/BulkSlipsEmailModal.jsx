import React, { useState, useMemo, useEffect, useRef } from "react";
import { Modal } from "@/Components/ui/modal";
import Button from "@/Components/ui/button/Button";
import toast from "react-hot-toast";
import axios from "axios";
import { croatianPlural } from "@/utils/slipMailing";
import { notifyMailingStarted } from "@/lib/mailingActivity";

const MAX_INVOICES_PER_SEND = 100;
const MAX_INVOICES_MESSAGE = "Možete poslati najviše 100 uplatnica odjednom.";

function formatMonthLabel(yyyyMm) {
    if (!yyyyMm) return "";
    const [y, m] = yyyyMm.split("-");
    const formatted = new Intl.DateTimeFormat("hr-HR", {
        month: "long",
        year: "numeric",
    }).format(new Date(Number(y), Number(m) - 1, 1));
    const clean = formatted.replace(/\.$/, "");
    return clean.charAt(0).toLocaleUpperCase("hr-HR") + clean.slice(1);
}

function readySentence(count) {
    const adjective = croatianPlural(count, "spremna je", "spremne su", "spremno je");
    const noun = croatianPlural(count, "uplatnica", "uplatnice", "uplatnica");
    return `${count} ${noun} ${adjective} za slanje.`;
}

function skipSentence(count) {
    const noun = croatianPlural(
        count,
        "uplatnica već je poslana",
        "uplatnice već su poslane",
        "uplatnica već je poslano",
    );
    const skipped = croatianPlural(count, "bit će preskočena", "bit će preskočene", "bit će preskočeno");
    return `${count} ${noun} i ${skipped}.`;
}

function sendButtonLabel(count) {
    const noun = croatianPlural(count, "uplatnicu", "uplatnice", "uplatnica");
    return `Pošalji ${count} ${noun}`;
}

/**
 * One row the members table or group page may pass through.
 * The modal no longer lists names; counts come from the preview.
 *
 * @typedef {object} SlipEmailMember
 * @property {number} id
 * @property {string} [first_name]
 * @property {string} [last_name]
 */

/**
 * Pre-flight for sending payment slips. The request only queues the batch.
 *
 * @param {object} props
 * @param {boolean} props.isOpen
 * @param {() => void} props.onClose
 * @param {'group' | 'members'} props.mode
 * @param {number} [props.groupId]
 * @param {string} [props.groupName]
 * @param {number[]} props.selectedMemberIds
 * @param {SlipEmailMember[]} [props.members]
 * @param {number} [props.groupTotalMembers]
 * @param {() => void} [props.onSuccess]
 */
export default function BulkSlipsEmailModal({
    isOpen,
    onClose,
    mode,
    groupId,
    groupName,
    selectedMemberIds,
    members: _members,
    groupTotalMembers,
    onSuccess,
}) {
    const [selectedMonth, setSelectedMonth] = useState(() => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, "0");
        return `${year}-${month}`;
    });
    const [sendScope, setSendScope] = useState(
        mode === "group" && selectedMemberIds.length === 0 ? "all" : "selected",
    );
    const [processing, setProcessing] = useState(false);
    const [preview, setPreview] = useState(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState(null);
    const [includeAlreadySent, setIncludeAlreadySent] = useState(false);

    const sendUrl = useMemo(() => {
        if (mode === "group") {
            return route("member-groups.bulk-send-slip-emails", groupId);
        }
        return route("members.bulkSendSlipEmails");
    }, [mode, groupId]);

    const previewUrl = useMemo(() => {
        if (mode === "group") {
            return route("member-groups.bulk-send-slip-emails.preview", groupId);
        }
        return route("members.bulkSendSlipEmails.preview");
    }, [mode, groupId]);

    const generateInvoicesUrl = useMemo(() => route("invoices.generate.index"), []);
    const monthLabel = useMemo(() => formatMonthLabel(selectedMonth), [selectedMonth]);

    const subtitle = mode === "group"
        ? `${groupName || "Grupa"} · ${monthLabel}`
        : `Odabrani članovi · ${monthLabel}`;

    const previewPayload = useMemo(() => {
        if (!selectedMonth) return null;

        if (mode === "group") {
            if (sendScope === "selected" && selectedMemberIds.length === 0) {
                return null;
            }
            return {
                month: selectedMonth,
                send_scope: sendScope,
                ...(sendScope === "selected" ? { member_ids: selectedMemberIds } : {}),
            };
        }

        if (selectedMemberIds.length === 0) return null;
        return {
            month: selectedMonth,
            member_ids: selectedMemberIds,
        };
    }, [mode, sendScope, selectedMemberIds, selectedMonth]);

    const requestIdRef = useRef(0);
    const previewKey = previewPayload ? JSON.stringify(previewPayload) : "";

    useEffect(() => {
        setIncludeAlreadySent(false);
    }, [previewKey, isOpen]);

    useEffect(() => {
        if (!isOpen) return;
        if (!previewPayload) {
            setPreview(null);
            setPreviewError(null);
            return;
        }

        const reqId = ++requestIdRef.current;
        setPreviewLoading(true);
        setPreviewError(null);

        const handle = setTimeout(() => {
            axios
                .post(previewUrl, previewPayload, {
                    headers: { Accept: "application/json" },
                })
                .then(({ data }) => {
                    if (reqId !== requestIdRef.current) return;
                    setPreview(data);
                })
                .catch((error) => {
                    if (reqId !== requestIdRef.current) return;
                    setPreviewError(error.response?.data?.message || "Ne mogu dohvatiti pregled.");
                    setPreview(null);
                })
                .finally(() => {
                    if (reqId !== requestIdRef.current) return;
                    setPreviewLoading(false);
                });
        }, 250);

        return () => clearTimeout(handle);
    }, [isOpen, previewPayload, previewUrl]);

    const hasPreview = preview && !preview.invalid_month;
    const noInvoicesAtAll = hasPreview && preview.invoices_count === 0;
    const someInvoices = hasPreview && preview.invoices_count > 0;
    const deliverable = preview?.deliverable_count ?? 0;
    const alreadySent = preview?.already_sent_count ?? 0;
    const sendCount = includeAlreadySent ? deliverable + alreadySent : deliverable;
    const overCap = someInvoices && preview.invoices_count > MAX_INVOICES_PER_SEND;
    const selectionMissing =
        (mode === "group" && sendScope === "selected" && selectedMemberIds.length === 0) ||
        (mode === "members" && selectedMemberIds.length === 0);
    const submitDisabled =
        processing || previewLoading || !someInvoices || overCap || sendCount === 0 || selectionMissing;

    const postSend = async () => {
        if (selectionMissing) {
            toast.error("Molimo odaberite barem jednog člana.");
            return;
        }

        if (overCap) {
            toast.error(MAX_INVOICES_MESSAGE);
            return;
        }

        const payload =
            mode === "group"
                ? {
                      month: selectedMonth,
                      send_scope: sendScope,
                      resend: includeAlreadySent,
                      ...(sendScope === "selected" ? { member_ids: selectedMemberIds } : {}),
                  }
                : {
                      month: selectedMonth,
                      member_ids: selectedMemberIds,
                      resend: includeAlreadySent,
                  };

        try {
            setProcessing(true);
            const { data } = await axios.post(sendUrl, payload, {
                headers: { Accept: "application/json" },
            });
            const queued = data?.queued ?? 0;
            const message = typeof data?.message === "string" && data.message.trim() !== ""
                ? data.message
                : queued > 0
                  ? `Slanje je pokrenuto. ${queued} uplatnica šalje se.`
                  : "Slanje nije pokrenuto.";

            if (queued > 0) {
                toast.success(message);
                if (data?.mailing_id) {
                    notifyMailingStarted();
                }
            } else {
                toast.error(message);
            }

            onSuccess?.();
            onClose?.();
        } catch (error) {
            const res = error.response;
            let msg = "Došlo je do greške pri slanju e-pošte.";

            if (res?.data) {
                if (typeof res.data.message === "string") {
                    msg = res.data.message;
                }
                if (res.data.errors) {
                    const flat = Object.values(res.data.errors).flat();
                    if (flat.length) {
                        msg = flat.join(" ");
                    }
                }
            }

            toast.error(msg);
        } finally {
            setProcessing(false);
        }
    };

    const countRows = hasPreview
        ? [
              ["Odabrani članovi", preview.members_total],
              ["Uplatnice pronađene", preview.invoices_count],
              ["Spremno za slanje", sendCount],
              ["Već poslano", preview.already_sent_count],
              ["Nedostaje e-mail", preview.members_without_invoice_email],
          ]
        : [];

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="max-w-[560px] m-4">
            <div className="no-scrollbar relative w-full max-w-[560px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                <h5 className="text-xl font-semibold text-gray-800 dark:text-white/90">Pošalji uplatnice</h5>
                <p className="mt-1 mb-6 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>

                {mode === "group" && (
                    <div className="mb-6 space-y-3">
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-300">Opseg slanja</p>
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="radio"
                                name="send_scope"
                                value="selected"
                                checked={sendScope === "selected"}
                                onChange={() => setSendScope("selected")}
                                className="text-brand-500 focus:ring-brand-500"
                            />
                            Odabrani članovi ({selectedMemberIds.length})
                        </label>
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="radio"
                                name="send_scope"
                                value="all"
                                checked={sendScope === "all"}
                                onChange={() => setSendScope("all")}
                                className="text-brand-500 focus:ring-brand-500"
                            />
                            Svi članovi grupe ({groupTotalMembers ?? "—"})
                        </label>
                    </div>
                )}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        void postSend();
                    }}
                    className="space-y-4"
                >
                    <div>
                        <label
                            htmlFor="slip-email-month"
                            className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Mjesec (dospijeće računa)
                        </label>
                        <input
                            type="month"
                            id="slip-email-month"
                            value={selectedMonth}
                            onChange={(e) => setSelectedMonth(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            required
                        />
                    </div>

                    <div className="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                        {selectionMissing && (
                            <p className="text-gray-500 dark:text-gray-400">
                                Odaberite članove ili pošaljite cijeloj grupi.
                            </p>
                        )}

                        {previewLoading && (
                            <p className="text-gray-500 dark:text-gray-400">Provjera računa za {monthLabel}…</p>
                        )}

                        {!previewLoading && previewError && (
                            <p className="text-amber-600 dark:text-amber-400">{previewError}</p>
                        )}

                        {!previewLoading && noInvoicesAtAll && (
                            <div className="space-y-2">
                                <p className="font-medium text-amber-700 dark:text-amber-400">
                                    Nema računa za {monthLabel}.
                                </p>
                                <p className="text-gray-600 dark:text-gray-400">
                                    Računi za odabrane članove još nisu generirani za ovaj mjesec. Generirajte ih
                                    prije slanja.
                                </p>
                                <a
                                    href={generateInvoicesUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-brand-600 underline hover:text-brand-700"
                                >
                                    Otvori generator računa →
                                </a>
                            </div>
                        )}

                        {!previewLoading && someInvoices && (
                            <div className="space-y-3">
                                <dl className="space-y-1.5">
                                    {countRows.map(([label, value]) => (
                                        <div key={label} className="flex items-center justify-between gap-4">
                                            <dt className="text-gray-500 dark:text-gray-400">{label}</dt>
                                            <dd className="font-medium text-gray-800 dark:text-white">{value}</dd>
                                        </div>
                                    ))}
                                </dl>
                                {overCap && (
                                    <p className="font-medium text-red-600 dark:text-red-400">{MAX_INVOICES_MESSAGE}</p>
                                )}
                                {sendCount > 0 && !overCap && (
                                    <p className="text-gray-800 dark:text-white/90">{readySentence(sendCount)}</p>
                                )}
                                {alreadySent > 0 && !includeAlreadySent && (
                                    <div className="space-y-2">
                                        <p className="text-gray-600 dark:text-gray-300">{skipSentence(alreadySent)}</p>
                                        <button
                                            type="button"
                                            onClick={() => setIncludeAlreadySent(true)}
                                            className="text-sm font-medium text-brand-600 hover:text-brand-700"
                                        >
                                            Ponovno uključi već poslane
                                        </button>
                                    </div>
                                )}
                                {alreadySent > 0 && includeAlreadySent && (
                                    <button
                                        type="button"
                                        onClick={() => setIncludeAlreadySent(false)}
                                        className="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    >
                                        Preskoči već poslane
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
                        <Button type="button" onClick={onClose} variant="outline" size="sm" disabled={processing}>
                            Odustani
                        </Button>
                        <Button type="submit" variant="primary" size="sm" disabled={submitDisabled}>
                            {processing ? "Pokretanje..." : sendCount > 0 ? sendButtonLabel(sendCount) : "Pošalji"}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
