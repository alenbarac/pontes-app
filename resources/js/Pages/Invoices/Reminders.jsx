import { useEffect, useMemo, useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import axios from "axios";
import toast from "react-hot-toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import { Table, TableHeader, TableBody, TableRow, TableCell } from "@/Components/ui/table";
import { Modal } from "@/Components/ui/modal";
import { croatianPlural, reminderMailingPresentation, slipMailingPresentation } from "@/utils/slipMailing";
import { notifyMailingStarted } from "@/lib/mailingActivity";

const headerClass = "px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase dark:text-gray-400";
const cellClass = "px-4 py-3 align-middle text-sm text-gray-700 dark:text-gray-300";

function messageFromError(error, fallback) {
    const data = error?.response?.data;
    if (!data) {
        return fallback;
    }
    if (data.errors) {
        const flat = Object.values(data.errors).flat();
        if (flat.length) {
            return flat.join(" ");
        }
    }
    return data.message || fallback;
}

export default function Reminders({ invoices = [], graceDays = 10 }) {
    const [selected, setSelected] = useState(() => idsSelectedByDefault(invoices));
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [sending, setSending] = useState(false);
    const [markingId, setMarkingId] = useState(null);

    useEffect(() => {
        setSelected(idsSelectedByDefault(invoices));
    }, [invoices]);

    const selectedRows = useMemo(
        () => invoices.filter((invoice) => selected.has(invoice.id)),
        [invoices, selected],
    );
    const ready = invoices.filter((invoice) => invoice.selectable && invoice.reminder_mailing?.status !== "sent");
    const allReadyChecked = ready.length > 0 && ready.every((invoice) => selected.has(invoice.id));
    const resend = selectedRows.some((invoice) => invoice.reminder_mailing?.status === "sent");

    const toggle = (invoice) => {
        if (!invoice.selectable) {
            return;
        }
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(invoice.id)) {
                next.delete(invoice.id);
            } else {
                next.add(invoice.id);
            }
            return next;
        });
    };

    const toggleAll = () => {
        setSelected(allReadyChecked ? new Set() : new Set(ready.map((invoice) => invoice.id)));
    };

    const markPaid = (invoice) => {
        setMarkingId(invoice.id);
        router.patch(route("invoices.markPaid", invoice.id), { stay_on_page: true }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash ?? {};
                if (flash.success) {
                    toast.success(flash.success);
                }
            },
            onError: () => {
                toast.error("Račun nije označen kao plaćen.");
            },
            onFinish: () => setMarkingId(null),
        });
    };

    const send = async () => {
        if (sending || selectedRows.length === 0) {
            return;
        }
        setSending(true);
        try {
            const { data } = await axios.post(
                route("invoices.bulkSendReminderEmails"),
                {
                    invoice_ids: selectedRows.map((invoice) => invoice.id),
                    resend,
                },
                { headers: { Accept: "application/json" } },
            );
            toast.success(data.message || "Slanje je pokrenuto.");
            if (data?.mailing_id) {
                notifyMailingStarted();
            }
            setConfirmOpen(false);
            router.reload({ preserveScroll: true });
        } catch (error) {
            toast.error(messageFromError(error, "Greška pri slanju podsjetnika."));
        } finally {
            setSending(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Podsjetnici" />
            <Breadcrumb pageName="Podsjetnici" />
            <ComponentCard
                title="Podsjetnici"
                desc={`Nepodmireni računi kojima je uplatnica već poslana, a rok je prošao prije više od ${graceDays} dana. Podsjetnik odlazi samo za označene redove.`}
            >
                {invoices.length === 0 ? (
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Nema računa koji čekaju podsjetnik.
                    </p>
                ) : (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Označeno {selectedRows.length} od {invoices.length}
                            </p>
                            <button
                                type="button"
                                disabled={selectedRows.length === 0 || sending}
                                onClick={() => setConfirmOpen(true)}
                                className="inline-flex items-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Pošalji podsjetnik
                            </button>
                        </div>
                        <div className="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/[0.05]">
                            <Table className="w-full">
                                <TableHeader>
                                    <TableRow className="border-b border-gray-200 dark:border-gray-700">
                                        <TableCell isHeader className={headerClass}>
                                            <input
                                                type="checkbox"
                                                checked={allReadyChecked}
                                                onChange={toggleAll}
                                                aria-label="Odaberi sve spremne"
                                            />
                                        </TableCell>
                                        {["Član", "Grupa", "Mjesec", "Iznos", "Rok", "E-mail", "Uplatnica", "Podsjetnik", ""].map((heading) => (
                                            <TableCell key={heading || "actions"} isHeader className={headerClass}>
                                                {heading}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invoices.map((invoice) => {
                                        const slip = slipMailingPresentation(invoice.slip_mailing);
                                        const reminder = reminderMailingPresentation(invoice.reminder_mailing);
                                        const canCheck = invoice.selectable;

                                        return (
                                            <TableRow key={invoice.id} className="border-b border-gray-100 dark:border-white/[0.05]">
                                                <TableCell className={cellClass}>
                                                    <input
                                                        type="checkbox"
                                                        checked={selected.has(invoice.id)}
                                                        disabled={!canCheck}
                                                        onChange={() => toggle(invoice)}
                                                        aria-label={`Odaberi ${invoice.member_name}`}
                                                    />
                                                </TableCell>
                                                <TableCell className={`${cellClass} font-medium text-gray-900 dark:text-white`}>
                                                    <Link href={route("members.show", invoice.member_id)} className="hover:text-brand-600">
                                                        {invoice.member_name || "Član"}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className={cellClass}>{invoice.group_name || "—"}</TableCell>
                                                <TableCell className={cellClass}>{invoice.month_label || "—"}</TableCell>
                                                <TableCell className={cellClass}>{invoice.amount_label}</TableCell>
                                                <TableCell className={cellClass}>{invoice.due_on}</TableCell>
                                                <TableCell className={cellClass}>
                                                    {invoice.invoice_email || <span className="text-gray-400">Nema adrese</span>}
                                                </TableCell>
                                                <TableCell className={cellClass}>{slip.label}</TableCell>
                                                <TableCell className={cellClass}>
                                                    {reminder?.label || "Nije poslan"}
                                                    {invoice.send_block && (
                                                        <div className="text-xs text-gray-500 dark:text-gray-400">{invoice.send_block}</div>
                                                    )}
                                                </TableCell>
                                                <TableCell className={cellClass}>
                                                    <button
                                                        type="button"
                                                        disabled={markingId === invoice.id}
                                                        onClick={() => markPaid(invoice)}
                                                        className="text-sm font-medium text-green-700 hover:text-green-800 disabled:opacity-50 dark:text-green-400"
                                                    >
                                                        {markingId === invoice.id ? "Spremanje..." : "Plaćeno"}
                                                    </button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                )}
            </ComponentCard>

            <Modal isOpen={confirmOpen} onClose={() => !sending && setConfirmOpen(false)} className="mx-4 w-full max-w-md p-6">
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Pošalji podsjetnik</h3>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Poslat će se {selectedRows.length}{" "}
                    {croatianPlural(selectedRows.length, "podsjetnik", "podsjetnika", "podsjetnika")}{" "}
                    s priloženom uplatnicom, na e-adresu za račune.
                    {resend && " Uključeni su i računi kojima je podsjetnik već poslan."}
                </p>
                <div className="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        disabled={sending}
                        onClick={() => setConfirmOpen(false)}
                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Odustani
                    </button>
                    <button
                        type="button"
                        disabled={sending}
                        onClick={send}
                        className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                    >
                        {sending ? "Pokretanje..." : "Pošalji"}
                    </button>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function idsSelectedByDefault(invoices) {
    return new Set(invoices.filter((invoice) => invoice.selected).map((invoice) => invoice.id));
}
