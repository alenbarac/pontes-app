import React, { useState } from "react";
import { format } from "date-fns";
import { Modal } from "@/Components/ui/modal";
import Radio from "@/Components/form/input/Radio";
import { router } from "@inertiajs/react";
import toast from "react-hot-toast";
import Button from "@/ui/button/Button";

const MemberWorkshopInvoices = ({ invoices, member, workshop }) => {
    const [showInvoiceModal, setShowInvoiceModal] = useState(false);
    const [activeInvoice, setActiveInvoice] = useState(null);
    const [modalStatus, setModalStatus] = useState("");

    if (!invoices || invoices.length === 0) {
        return (
            <div className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Nema računa za ovu radionicu.
            </div>
        );
    }

    const openInvoiceDetails = (invoice) => {
        setActiveInvoice(invoice);
        setModalStatus(invoice?.payment_status || "");
        setShowInvoiceModal(true);
    };

    const closeInvoiceDetails = () => {
        setShowInvoiceModal(false);
        setActiveInvoice(null);
        setModalStatus("");
    };

    const saveInvoiceStatus = () => {
        if (!activeInvoice || !modalStatus) return;
        
        // Store invoice data before closing modal
        const invoiceToUpdate = activeInvoice;
        const currentStatus = modalStatus;
        
        // Close modal immediately for better UX
        closeInvoiceDetails();
        
        if (currentStatus === "Plaćeno") {
            router.patch(
                route("invoices.markPaid", invoiceToUpdate.id),
                { stay_on_page: true },
                {
                    preserveScroll: true,
                    preserveState: false, // Allow page to refresh with new data
                    onSuccess: () => {
                        toast.success("Status računa promjenjen");
                    },
                    onError: () => {
                        toast.error("Greška pri ažuriranju statusa");
                        // Reopen modal on error
                        openInvoiceDetails(invoiceToUpdate);
                    },
                },
            );
        } else {
            router.patch(
                route("invoices.updateStatus", invoiceToUpdate.id),
                { status: "Otvoreno", stay_on_page: true },
                {
                    preserveScroll: true,
                    preserveState: false, // Allow page to refresh with new data
                    onSuccess: () => {
                        toast.success("Status računa promjenjen");
                    },
                    onError: () => {
                        toast.error("Greška pri ažuriranju statusa");
                        // Reopen modal on error
                        openInvoiceDetails(invoiceToUpdate);
                    },
                },
            );
        }
    };

    return (
        <div className="mt-4">
            <div className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                Računi ({invoices.length})
            </div>
            <div className="space-y-2">
                {invoices.map((invoice) => (
                    <div
                        key={invoice.id}
                        onClick={() => openInvoiceDetails(invoice)}
                        className="p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition"
                    >
                        <div className="flex items-center justify-between">
                            <div className="flex-1">
                                <div className="flex items-center gap-3 mb-2">
                                    <span className="text-sm font-medium text-gray-900 dark:text-white">
                                        {invoice.reference_code}
                                    </span>
                                    <span
                                        className={`inline-block px-2 py-1 text-xs rounded ${
                                            invoice.payment_status === "Plaćeno"
                                                ? "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                                : invoice.payment_status === "Opomeni"
                                                  ? "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400"
                                                  : "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400"
                                        }`}
                                    >
                                        {invoice.payment_status}
                                    </span>
                                </div>
                                <div className="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span>
                                        Dospijeće:{" "}
                                        {format(
                                            new Date(invoice.due_date),
                                            "dd.MM.yyyy.",
                                        )}
                                    </span>
                                    <span>
                                        Iznos: {Number(invoice.amount_due).toFixed(2)} €
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Invoice Details Modal */}
            <Modal
                isOpen={showInvoiceModal}
                onClose={closeInvoiceDetails}
                className="max-w-5xl w-full mx-4 p-6"
            >
                {activeInvoice && (
                    <div>
                        <div className="flex items-start justify-between mb-4">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    Detalji računa
                                </h3>
                                <p className="text-sm text-gray-500">
                                    #{activeInvoice.reference_code}
                                </p>
                            </div>
                            <button
                                onClick={closeInvoiceDetails}
                                className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                aria-label="Zatvori"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            {/* Left: Meta (2 columns, 3 items) */}
                            <div className="sm:col-span-2">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Član
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {member?.first_name} {member?.last_name}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Radionica
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {workshop?.name || activeInvoice.workshop?.name || "-"}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Grupa
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {(() => {
                                                const wId = workshop?.id || activeInvoice.workshop_id;
                                                const groups = member?.workshopGroups || member?.workshop_groups;
                                                if (Array.isArray(groups)) {
                                                    const match = groups.find(
                                                        (g) => g.workshop_id === wId,
                                                    );
                                                    return (
                                                        match?.group?.name ||
                                                        match?.group_name ||
                                                        "-"
                                                    );
                                                }
                                                return "-";
                                            })()}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Iznos
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {Number(activeInvoice.amount_due).toFixed(2)} €
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Dospijeće
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {format(
                                                new Date(activeInvoice.due_date),
                                                "dd.MM.yyyy.",
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Referenca
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {activeInvoice.reference_code}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Plaćeno
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {Number(activeInvoice.amount_paid || 0).toFixed(2)} €
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-xs uppercase text-gray-500">
                                        Napomena
                                    </div>
                                    <div className="text-sm text-gray-900 dark:text-gray-100">
                                        {activeInvoice.notes || "—"}
                                    </div>
                                </div>
                            </div>

                            {/* Right: Actions with status toggle */}
                            <div className="flex flex-col gap-4">
                                <div>
                                    <div className="text-xs uppercase text-gray-500 mb-2">
                                        Status
                                    </div>
                                    <div className="flex items-center gap-6">
                                        <Radio
                                            id="status-paid"
                                            name="invoice-status"
                                            value="Plaćeno"
                                            checked={modalStatus === "Plaćeno"}
                                            label="Plaćeno"
                                            onChange={setModalStatus}
                                            className=""
                                        />
                                        <Radio
                                            id="status-open"
                                            name="invoice-status"
                                            value="Otvoreno"
                                            checked={modalStatus === "Otvoreno"}
                                            label="Otvoreno"
                                            onChange={setModalStatus}
                                        />
                                    </div>
                                </div>

                                <a
                                    href={route("invoices.slip", activeInvoice.id)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium border rounded-lg hover:bg-gray-50 dark:border-gray-700"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    Uplatnica (PDF)
                                </a>
                                <button
                                    disabled
                                    className="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium border rounded-lg text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed"
                                    title="Uskoro dostupno"
                                >
                                    Pošalji e-mail
                                </button>

                                <div className="flex items-center justify-end gap-2 pt-2">
                                    <Button
                                        onClick={closeInvoiceDetails}
                                        variant="outline"
                                        size="sm"
                                    >
                                        Zatvori
                                    </Button>
                                    <Button
                                        onClick={saveInvoiceStatus}
                                        variant="primary"
                                        size="sm"
                                    >
                                        Spremi status
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
};

export default MemberWorkshopInvoices;

