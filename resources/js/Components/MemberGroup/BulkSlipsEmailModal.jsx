import React, { useState, useMemo, useEffect, useRef } from "react";
import { Modal } from "@/Components/ui/modal";
import Button from "@/Components/ui/button/Button";
import toast from "react-hot-toast";
import axios from "axios";

const MAX_INVOICES_PER_SEND = 100;
const MAX_INVOICES_MESSAGE =
    "Možete poslati najviše 100 uplatnica odjednom.";

function croatianPlural(count, one, few, many) {
    const mod10 = count % 10;
    const mod100 = count % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
}

function alreadySentPreview(count) {
    const noun = croatianPlural(count, "uplatnica je već poslana", "uplatnice su već poslane", "uplatnica je već poslano");
    const skipped = croatianPlural(count, "bit će preskočena", "bit će preskočene", "bit će preskočeno");
    return `${count} ${noun} i ${skipped}, osim ako potvrdite ponovno slanje.`;
}

function alreadySentConfirm(count) {
    const noun = croatianPlural(
        count,
        "uplatnica je već poslana.",
        "uplatnice su već poslane.",
        "uplatnica je već poslano.",
    );
    return `${count} ${noun} Potvrdom će slanje biti pokrenuto još jednom.`;
}

function uplatniceAfterSlanje(count) {
    return croatianPlural(count, "uplatnice", "uplatnice", "uplatnica");
}

function queueToastMessage(data) {
    if (typeof data?.message === "string" && data.message.trim() !== "") {
        return data.message;
    }

    const parts = [];
    const queued = data?.queued ?? 0;
    if (queued > 0) {
        parts.push(
            `Pokrenuto je slanje ${queued} ${uplatniceAfterSlanje(queued)}.`,
        );
    }

    const noEmail = data?.skipped_no_email?.length ?? 0;
    const already = data?.skipped_already_sent?.length ?? 0;
    const failed = data?.failed?.length ?? 0;
    if (noEmail > 0) {
        parts.push(
            `${noEmail} ${croatianPlural(noEmail, "uplatnica nema e-mail i zato je preskočena", "uplatnice nemaju e-mail i zato su preskočene", "uplatnica nema e-mail i zato su preskočene")}.`,
        );
    }
    if (already > 0) {
        parts.push(
            `${already} ${croatianPlural(already, "uplatnica je već poslana i zato je preskočena", "uplatnice su već poslane i zato su preskočene", "uplatnica je već poslano i zato su preskočene")}.`,
        );
    }
    if (failed > 0) {
        parts.push(
            `${failed} ${croatianPlural(failed, "uplatnica nije mogla krenuti na slanje", "uplatnice nisu mogle krenuti na slanje", "uplatnica nije moglo krenuti na slanje")}.`,
        );
    }

    return parts.length > 0 ? parts.join(" ") : "Slanje nije pokrenuto.";
}

/**
 * Send payment-slip e-mails by month with a pre-flight preview.
 *
 * Props:
 *  - mode: 'group' | 'members'
 *  - groupId: number (only when mode === 'group')
 *  - selectedMemberIds: number[]
 *  - members: { id, first_name, last_name }[] — preferably the full selected
 *    rows (used to render the preview list); when this is just the current
 *    page of a paginated table, members from other pages are still sent but
 *    not shown in the list.
 *  - groupTotalMembers: number (total members in group, only for mode='group')
 */
export default function BulkSlipsEmailModal({
    isOpen,
    onClose,
    mode,
    groupId,
    selectedMemberIds,
    members,
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
    const [resendStep, setResendStep] = useState(false);

    const selectedMembersData = useMemo(() => {
        const ids = new Set(selectedMemberIds);
        return members.filter((m) => ids.has(m.id));
    }, [members, selectedMemberIds]);

    const hiddenSelectedCount = Math.max(
        0,
        selectedMemberIds.length - selectedMembersData.length,
    );

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

    const monthLabel = useMemo(() => {
        if (!selectedMonth) return "";
        const [y, m] = selectedMonth.split("-");
        const date = new Date(Number(y), Number(m) - 1, 1);
        return new Intl.DateTimeFormat("hr-HR", {
            month: "long",
            year: "numeric",
        }).format(date);
    }, [selectedMonth]);

    const previewPayload = useMemo(() => {
        if (!selectedMonth) return null;

        if (mode === "group") {
            if (sendScope === "selected" && selectedMemberIds.length === 0) {
                return null;
            }
            return {
                month: selectedMonth,
                send_scope: sendScope,
                ...(sendScope === "selected"
                    ? { member_ids: selectedMemberIds }
                    : {}),
            };
        }

        if (selectedMemberIds.length === 0) return null;
        return {
            month: selectedMonth,
            member_ids: selectedMemberIds,
        };
    }, [mode, sendScope, selectedMemberIds, selectedMonth]);

    const requestIdRef = useRef(0);

    useEffect(() => {
        if (!isOpen) {
            setResendStep(false);
        }
    }, [isOpen]);

    const previewKey = previewPayload ? JSON.stringify(previewPayload) : "";

    useEffect(() => {
        setResendStep(false);
    }, [previewKey]);

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
                    const msg =
                        error.response?.data?.message ||
                        "Ne mogu dohvatiti pregled.";
                    setPreviewError(msg);
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
    const overCap = someInvoices && preview.invoices_count > MAX_INVOICES_PER_SEND;
    const selectionMissing =
        (mode === "group" &&
            sendScope === "selected" &&
            selectedMemberIds.length === 0) ||
        (mode === "members" && selectedMemberIds.length === 0);
    const submitDisabled =
        processing ||
        previewLoading ||
        !someInvoices ||
        overCap ||
        deliverable === 0 ||
        selectionMissing;

    const postSend = async (resend) => {
        if (mode === "group" && sendScope === "selected" && selectedMemberIds.length === 0) {
            toast.error("Molimo odaberite barem jednog člana.");
            return;
        }

        if (mode === "members" && selectedMemberIds.length === 0) {
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
                      resend,
                      ...(sendScope === "selected"
                          ? { member_ids: selectedMemberIds }
                          : {}),
                  }
                : {
                      month: selectedMonth,
                      member_ids: selectedMemberIds,
                      resend,
                  };

        try {
            setProcessing(true);
            const { data } = await axios.post(sendUrl, payload, {
                headers: { Accept: "application/json" },
            });
            const message = queueToastMessage(data);
            const failedCount = data?.failed?.length ?? 0;
            if (failedCount > 0 && (data?.queued ?? 0) === 0) {
                toast.error(message);
            } else {
                toast.success(message);
            }
            setResendStep(false);
            onSuccess?.();
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

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (resendStep) {
            await postSend(true);
            return;
        }

        if (deliverable === 0) {
            if (alreadySent > 0 && !overCap) {
                setResendStep(true);
            }
            return;
        }

        await postSend(false);
    };

    const showSelectedList = mode === "members" || sendScope === "selected";

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="max-w-[600px] m-4">
            <div className="no-scrollbar relative w-full max-w-[600px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                    Pošalji uplatnice e-poštom
                </h5>

                {mode === "group" && (
                    <div className="mb-6 space-y-3">
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Opseg slanja
                        </p>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
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
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
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

                <div className="mb-6">
                    <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        {mode === "group" && sendScope === "all" ? (
                            <>
                                Slanje za sve članove grupe (ukupno{" "}
                                <strong>{groupTotalMembers ?? "—"}</strong>).
                            </>
                        ) : (
                            <>
                                Odabrano je{" "}
                                <strong>{selectedMemberIds.length}</strong>{" "}
                                članova:
                            </>
                        )}
                    </p>
                    {showSelectedList && (
                        <>
                            <div className="max-h-32 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-800">
                                <ul className="space-y-1">
                                    {selectedMembersData.map((member) => (
                                        <li
                                            key={member.id}
                                            className="text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            {member.first_name}{" "}
                                            {member.last_name}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            {hiddenSelectedCount > 0 && (
                                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {hiddenSelectedCount} odabranih članova nije
                                    prikazano (s drugih stranica). Bit će
                                    uključeni u slanje.
                                </p>
                            )}
                        </>
                    )}
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label
                            htmlFor="slip-email-month"
                            className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
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

                    <div className="rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-sm">
                        {previewLoading && (
                            <p className="text-gray-500 dark:text-gray-400">
                                Provjera računa za {monthLabel}…
                            </p>
                        )}

                        {!previewLoading && previewError && (
                            <p className="text-amber-600 dark:text-amber-400">
                                {previewError}
                            </p>
                        )}

                        {!previewLoading && noInvoicesAtAll && (
                            <div className="space-y-2">
                                <p className="text-amber-700 dark:text-amber-400 font-medium">
                                    Nema računa za {monthLabel}.
                                </p>
                                <p className="text-gray-600 dark:text-gray-400">
                                    Računi za odabrane članove još nisu
                                    generirani za ovaj mjesec. Generirajte ih
                                    prije slanja.
                                </p>
                                <a
                                    href={generateInvoicesUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-brand-600 hover:text-brand-700 underline"
                                >
                                    Otvori generator računa →
                                </a>
                            </div>
                        )}

                        {!previewLoading && someInvoices && (
                            <div className="space-y-1.5">
                                <p className="text-gray-700 dark:text-gray-300">
                                    <strong>{deliverable}</strong>{" "}
                                    {croatianPlural(
                                        deliverable,
                                        "uplatnica pripremljena je za slanje",
                                        "uplatnice pripremljene su za slanje",
                                        "uplatnica pripremljeno je za slanje",
                                    )}{" "}
                                    (od {preview.invoices_count} pronađenih
                                    računa za {monthLabel}).
                                </p>
                                {overCap && (
                                    <p className="text-red-600 dark:text-red-400 font-medium">
                                        {MAX_INVOICES_MESSAGE}
                                    </p>
                                )}
                                {alreadySent > 0 && (
                                    <p className="text-amber-700 dark:text-amber-400">
                                        {alreadySentPreview(alreadySent)}
                                    </p>
                                )}
                                {preview.members_without_invoice > 0 && (
                                    <p className="text-amber-700 dark:text-amber-400">
                                        {preview.members_without_invoice}{" "}
                                        {preview.members_without_invoice === 1
                                            ? "član nema"
                                            : "članova nema"}{" "}
                                        račun za ovaj mjesec.{" "}
                                        <a
                                            href={generateInvoicesUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-brand-600 hover:text-brand-700 underline"
                                        >
                                            Generiraj račune →
                                        </a>
                                    </p>
                                )}
                                {preview.members_without_invoice_email > 0 && (
                                    <p className="text-amber-700 dark:text-amber-400">
                                        {preview.members_without_invoice_email}{" "}
                                        {preview.members_without_invoice_email ===
                                        1
                                            ? "član nema"
                                            : "članova nema"}{" "}
                                        polje „Email za račune” — bit će
                                        preskočeni.
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    {resendStep && (
                        <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                            <p className="font-medium mb-1">
                                Potvrda ponovnog slanja
                            </p>
                            <p>
                                {alreadySentConfirm(alreadySent)}
                                {deliverable > 0
                                    ? " Pokrenut će se i slanje uplatnica koje još nisu poslane."
                                    : ""}
                            </p>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center justify-end gap-3 mt-6">
                        {resendStep ? (
                            <>
                                <Button
                                    type="button"
                                    onClick={() => setResendStep(false)}
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Natrag
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => {
                                        void postSend(true);
                                    }}
                                    variant="primary"
                                    size="sm"
                                    disabled={processing || overCap}
                                >
                                    {processing
                                        ? "Slanje je u tijeku..."
                                        : "Potvrdi ponovno slanje"}
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button
                                    type="button"
                                    onClick={onClose}
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Odustani
                                </Button>
                                {alreadySent > 0 && (
                                    <Button
                                        type="button"
                                        onClick={() => setResendStep(true)}
                                        variant={
                                            deliverable > 0
                                                ? "outline"
                                                : "primary"
                                        }
                                        size="sm"
                                        disabled={
                                            processing ||
                                            previewLoading ||
                                            overCap ||
                                            !someInvoices
                                        }
                                    >
                                        Ponovno pošalji
                                    </Button>
                                )}
                                {(deliverable > 0 || alreadySent === 0) && (
                                    <Button
                                        type="submit"
                                        variant="primary"
                                        size="sm"
                                        disabled={submitDisabled}
                                    >
                                        {processing
                                            ? "Slanje je u tijeku..."
                                            : someInvoices && deliverable > 0
                                              ? `Pošalji ${deliverable} uplatnica`
                                              : "Pošalji"}
                                    </Button>
                                )}
                            </>
                        )}
                    </div>
                </form>
            </div>
        </Modal>
    );
}
