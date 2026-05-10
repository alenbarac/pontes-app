import React, { useState, useMemo, useEffect, useRef } from "react";
import { Modal } from "@/Components/ui/modal";
import Button from "@/Components/ui/button/Button";
import toast from "react-hot-toast";
import axios from "axios";

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
    const submitDisabled =
        processing ||
        previewLoading ||
        !someInvoices ||
        deliverable === 0 ||
        (mode === "group" &&
            sendScope === "selected" &&
            selectedMemberIds.length === 0) ||
        (mode === "members" && selectedMemberIds.length === 0);

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (mode === "group" && sendScope === "selected" && selectedMemberIds.length === 0) {
            toast.error("Molimo odaberite barem jednog člana.");
            return;
        }

        if (mode === "members" && selectedMemberIds.length === 0) {
            toast.error("Molimo odaberite barem jednog člana.");
            return;
        }

        const payload =
            mode === "group"
                ? {
                      month: selectedMonth,
                      send_scope: sendScope,
                      ...(sendScope === "selected"
                          ? { member_ids: selectedMemberIds }
                          : {}),
                  }
                : {
                      month: selectedMonth,
                      member_ids: selectedMemberIds,
                  };

        try {
            setProcessing(true);
            const { data } = await axios.post(sendUrl, payload, {
                headers: { Accept: "application/json" },
            });
            toast.success(data.message || "E-poruke poslane.");
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
                                    {deliverable === 1
                                        ? "uplatnica"
                                        : "uplatnica/e"}{" "}
                                    spremno za slanje (od{" "}
                                    {preview.invoices_count} pronađenih računa
                                    za {monthLabel}).
                                </p>
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

                    <div className="flex items-center justify-end gap-3 mt-6">
                        <Button
                            type="button"
                            onClick={onClose}
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            Odustani
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            size="sm"
                            disabled={submitDisabled}
                        >
                            {processing
                                ? "Slanje..."
                                : someInvoices && deliverable > 0
                                  ? `Pošalji ${deliverable} uplatnica`
                                  : "Pošalji"}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
