import React, { useState, useEffect } from "react";
import { format } from "date-fns";
import { Modal } from "@/Components/ui/modal";
import Radio from "@/Components/form/input/Radio";
import { router } from "@inertiajs/react";
import toast from "react-hot-toast";
import Button from "@/ui/button/Button";
import { useModal } from "@/hooks/useModal";
import axios from "axios";
import Flatpickr from "react-flatpickr";
import Label from "@/Components/form/Label";
import Input from "@/Components/form/input/InputField";
import { CalendarDaysIcon, TrashIcon, Cog8ToothIcon } from "@heroicons/react/24/outline";
import { Table, TableHeader, TableBody, TableRow, TableCell } from "@/Components/ui/table";

const MemberWorkshopInvoices = ({ invoices, member, workshop }) => {
    const [showInvoiceModal, setShowInvoiceModal] = useState(false);
    const [activeInvoice, setActiveInvoice] = useState(null);
    const [modalStatus, setModalStatus] = useState("");
    
    // Session invoice generation state
    const sessionInvoiceModal = useModal();
    const confirmSessionInvoiceModal = useModal();
    const [sessionDate, setSessionDate] = useState("");
    const [sessionAmount, setSessionAmount] = useState("");
    const [defaultAmount, setDefaultAmount] = useState(0);
    const [generatingSession, setGeneratingSession] = useState(false);
    const [previewingSession, setPreviewingSession] = useState(false);
    
    // Delete invoice state
    const deleteInvoiceModal = useModal();
    const [invoiceToDelete, setInvoiceToDelete] = useState(null);
    
    // Check if this is an individual counseling workshop
    const isIndividualCounseling = workshop?.type === 'Individualno' || 
                                   (workshop?.name && workshop.name.toLowerCase().includes('individualno'));

    // Load default amount when opening session invoice modal
    useEffect(() => {
        if (sessionInvoiceModal.isOpen && isIndividualCounseling && !defaultAmount) {
            previewSessionInvoice();
        }
    }, [sessionInvoiceModal.isOpen]);

    const previewSessionInvoice = async () => {
        if (!sessionDate) {
            return;
        }

        setPreviewingSession(true);
        try {
            const response = await axios.post(
                route("members.invoices.session.preview", member.id),
                {
                    workshop_id: workshop.id,
                    session_date: sessionDate,
                }
            );

            if (response.data.success) {
                setDefaultAmount(response.data.preview.default_amount);
                if (!sessionAmount) {
                    setSessionAmount(response.data.preview.default_amount.toString());
                }
            }
        } catch (error) {
            console.error("Preview error:", error);
        } finally {
            setPreviewingSession(false);
        }
    };

    const handleSessionDateChange = (dates) => {
        if (dates && dates.length > 0) {
            const dateStr = dates[0].toISOString().split('T')[0];
            setSessionDate(dateStr);
            // Auto-preview when date changes
            if (isIndividualCounseling) {
                setTimeout(() => previewSessionInvoice(), 300);
            }
        }
    };

    const openSessionInvoiceModal = () => {
        setSessionDate("");
        setSessionAmount("");
        setDefaultAmount(0);
        sessionInvoiceModal.openModal();
    };

    const closeSessionInvoiceModal = () => {
        sessionInvoiceModal.closeModal();
        setSessionDate("");
        setSessionAmount("");
        setDefaultAmount(0);
    };

    const handleGenerateSessionInvoice = () => {
        if (!sessionDate) {
            toast.error("Molimo odaberite datum sastanka.");
            return;
        }

        if (!sessionAmount || parseFloat(sessionAmount) <= 0) {
            toast.error("Molimo unesite valjani iznos.");
            return;
        }

        confirmSessionInvoiceModal.openModal();
    };

    const confirmGenerateSessionInvoice = async () => {
        setGeneratingSession(true);
        confirmSessionInvoiceModal.closeModal();

        try {
            const response = await axios.post(
                route("members.invoices.session.generate", member.id),
                {
                    workshop_id: workshop.id,
                    session_date: sessionDate,
                    amount: parseFloat(sessionAmount),
                }
            );

            if (response.data.success) {
                toast.success("Račun uspješno generiran!");
                closeSessionInvoiceModal();
                // Refresh the page to show new invoice
                router.reload({ only: ['invoicesByWorkshop'] });
            } else {
                toast.error(response.data.message || "Greška pri generiranju računa.");
            }
        } catch (error) {
            const errorMessage =
                error.response?.data?.message ||
                error.response?.data?.error ||
                error.message ||
                "Greška pri generiranju računa.";
            toast.error(errorMessage);
        } finally {
            setGeneratingSession(false);
        }
    };

    const hasInvoices = invoices && invoices.length > 0;

    const openInvoiceDetails = (invoice) => {
        setActiveInvoice(invoice);
        setModalStatus(invoice?.payment_status || "");
        setShowInvoiceModal(true);
    };

    const handleDeleteInvoice = (invoice, e) => {
        e.stopPropagation(); // Prevent opening invoice details modal
        setInvoiceToDelete(invoice);
        deleteInvoiceModal.openModal();
    };

    const confirmDeleteInvoice = () => {
        if (!invoiceToDelete) return;

        router.delete(
            route("invoices.destroy", invoiceToDelete.id),
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success("Račun uspješno obrisan");
                    deleteInvoiceModal.closeModal();
                    setInvoiceToDelete(null);
                },
                onError: () => {
                    toast.error("Greška pri brisanju računa");
                    deleteInvoiceModal.closeModal();
                    setInvoiceToDelete(null);
                },
            }
        );
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
        <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-800">
            <div className="flex items-center justify-between mb-4">
                <div className="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Računi {hasInvoices && `(${invoices.length})`}
                </div>
                {isIndividualCounseling && (
                    <Button
                        onClick={openSessionInvoiceModal}
                        variant="primary"
                        size="xs"
                    >
                        Generiraj račun
                    </Button>
                )}
            </div>
            
            {!hasInvoices && (
                <div className="text-sm text-gray-500 dark:text-gray-400 py-4">
                    Nema računa za ovu radionicu.
                </div>
            )}
            
            {hasInvoices && (
                <div className="overflow-x-auto">
                    <Table className="w-full">
                        <TableHeader>
                            <TableRow className="border-b border-gray-200 dark:border-gray-700">
                                <TableCell isHeader className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Referenca
                                </TableCell>
                                <TableCell isHeader className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Status
                                </TableCell>
                                <TableCell isHeader className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Dospijeće
                                </TableCell>
                                <TableCell isHeader className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Iznos
                                </TableCell>
                                <TableCell isHeader className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Akcije
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoices.map((invoice) => (
                                <TableRow
                                    key={invoice.id}
                                    className="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition"
                                >
                                    <TableCell className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                        {invoice.reference_code}
                                    </TableCell>
                                    <TableCell className="px-4 py-3">
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
                                    </TableCell>
                                    <TableCell className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {format(
                                            new Date(invoice.due_date),
                                            "dd.MM.yyyy.",
                                        )}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {Number(invoice.amount_due).toFixed(2)} €
                                    </TableCell>
                                    <TableCell className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <button
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    openInvoiceDetails(invoice);
                                                }}
                                                className="inline-flex items-center justify-center p-1.5 text-gray-600 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:bg-gray-800/50 rounded transition"
                                                title="Detalji računa"
                                            >
                                                <Cog8ToothIcon className="h-4 w-4" />
                                            </button>
                                            <button
                                                onClick={(e) => handleDeleteInvoice(invoice, e)}
                                                className="inline-flex items-center justify-center p-1.5 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition"
                                                title="Obriši račun"
                                            >
                                                <TrashIcon className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            {/* Session Invoice Generation Modal */}
            <Modal
                isOpen={sessionInvoiceModal.isOpen}
                onClose={closeSessionInvoiceModal}
                className="max-w-[600px] w-full mx-4 p-6"
            >
                <div>
                    <div className="flex items-start justify-between mb-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                Generiraj račun za sastanak
                            </h3>
                            <p className="text-sm text-gray-500">
                                {workshop?.name}
                            </p>
                        </div>
                        <button
                            onClick={closeSessionInvoiceModal}
                            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            aria-label="Zatvori"
                        >
                            ✕
                        </button>
                    </div>

                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="session-date">Datum sastanka *</Label>
                            <div className="relative w-full flatpickr-wrapper">
                                <Flatpickr
                                    value={sessionDate}
                                    onChange={handleSessionDateChange}
                                    options={{
                                        dateFormat: "Y-m-d",
                                        maxDate: "today",
                                    }}
                                    placeholder="Odaberite datum"
                                    className="h-11 w-full rounded-lg border appearance-none px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:focus:border-brand-800"
                                />
                                <span className="absolute text-gray-500 -translate-y-1/2 pointer-events-none right-3 top-1/2 dark:text-gray-400">
                                    <CalendarDaysIcon className="size-6" />
                                </span>
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="session-amount">Iznos (EUR) *</Label>
                            <Input
                                type="number"
                                id="session-amount"
                                value={sessionAmount}
                                onChange={(e) => setSessionAmount(e.target.value)}
                                placeholder={defaultAmount ? `${defaultAmount.toFixed(2)}` : "0.00"}
                                step="0.01"
                                min="0"
                            />
                            {defaultAmount > 0 && (
                                <p className="mt-1 text-xs text-gray-500">
                                    Preporučeni iznos: {defaultAmount.toFixed(2)} EUR
                                    {defaultAmount === 50 ? " (član Dramske radionice)" : " (nije član Dramske radionice)"}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 mt-6">
                        <Button
                            onClick={closeSessionInvoiceModal}
                            variant="outline"
                            size="sm"
                        >
                            Odustani
                        </Button>
                        <Button
                            onClick={handleGenerateSessionInvoice}
                            variant="primary"
                            size="sm"
                            disabled={!sessionDate || !sessionAmount || generatingSession || previewingSession}
                        >
                            {generatingSession ? "Generiranje..." : "Generiraj račun"}
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Confirmation Modal for Session Invoice */}
            <Modal
                isOpen={confirmSessionInvoiceModal.isOpen}
                onClose={confirmSessionInvoiceModal.closeModal}
                className="max-w-[500px] p-5 lg:p-10"
            >
                <div className="text-center">
                    <h4 className="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90 sm:text-title-sm">
                        Generiraj račun
                    </h4>
                    <p className="text-sm leading-6 text-gray-500 dark:text-gray-400 mb-4">
                        Jeste li sigurni da želite generirati račun za sastanak?
                    </p>
                    {sessionDate && (
                        <div className="mb-4 text-sm text-gray-700 dark:text-gray-300">
                            <p><strong>Datum:</strong> {format(new Date(sessionDate), "dd.MM.yyyy.")}</p>
                            <p><strong>Iznos:</strong> {Number(sessionAmount || 0).toFixed(2)} EUR</p>
                        </div>
                    )}
                    <div className="flex items-center justify-center w-full gap-3 mt-7">
                        <Button
                            onClick={confirmSessionInvoiceModal.closeModal}
                            variant="outline"
                            size="sm"
                        >
                            Odustani
                        </Button>
                        <Button
                            onClick={confirmGenerateSessionInvoice}
                            variant="primary"
                            size="sm"
                        >
                            Da, generiraj
                        </Button>
                    </div>
                </div>
            </Modal>

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
                                <button
                                    onClick={() => {
                                        setInvoiceToDelete(activeInvoice);
                                        closeInvoiceDetails();
                                        deleteInvoiceModal.openModal();
                                    }}
                                    className="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium border rounded-lg text-red-600 border-red-200 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 transition"
                                >
                                    <TrashIcon className="h-4 w-4" />
                                    Brisanje računa
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

            {/* Delete Invoice Confirmation Modal */}
            <Modal
                isOpen={deleteInvoiceModal.isOpen}
                onClose={() => {
                    deleteInvoiceModal.closeModal();
                    setInvoiceToDelete(null);
                }}
                className="max-w-[500px] p-5 lg:p-10"
            >
                <div className="text-center">
                    <div className="relative flex items-center justify-center z-1 mb-7">
                        <svg
                            className="fill-error-50 dark:fill-error-500/15"
                            width="90"
                            height="90"
                            viewBox="0 0 90 90"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M34.364 6.85053C38.6205 -2.28351 51.3795 -2.28351 55.636 6.85053C58.0129 11.951 63.5594 14.6722 68.9556 13.3853C78.6192 11.0807 86.5743 21.2433 82.2185 30.3287C79.7862 35.402 81.1561 41.5165 85.5082 45.0122C93.3019 51.2725 90.4628 63.9451 80.7747 66.1403C75.3648 67.3661 71.5265 72.2695 71.5572 77.9156C71.6123 88.0265 60.1169 93.6664 52.3918 87.3184C48.0781 83.7737 41.9219 83.7737 37.6082 87.3184C29.8831 93.6664 18.3877 88.0266 18.4428 77.9156C18.4735 72.2695 14.6352 67.3661 9.22531 66.1403C-0.462787 63.9451 -3.30193 51.2725 4.49185 45.0122C8.84391 41.5165 10.2138 35.402 7.78151 30.3287C3.42572 21.2433 11.3808 11.0807 21.0444 13.3853C26.4406 14.6722 31.9871 11.951 34.364 6.85053Z"
                                fill=""
                                fillOpacity=""
                            />
                        </svg>

                        <span className="absolute -translate-x-1/2 -translate-y-1/2 left-1/2 top-1/2">
                            <svg
                                className="fill-error-600 dark:fill-error-500"
                                width="38"
                                height="38"
                                viewBox="0 0 38 38"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M9.62684 11.7496C9.04105 11.1638 9.04105 10.2141 9.62684 9.6283C10.2126 9.04252 11.1624 9.04252 11.7482 9.6283L18.9985 16.8786L26.2485 9.62851C26.8343 9.04273 27.7841 9.04273 28.3699 9.62851C28.9556 10.2143 28.9556 11.164 28.3699 11.7498L21.1198 18.9999L28.3699 26.25C28.9556 26.8358 28.9556 27.7855 28.3699 28.3713C27.7841 28.9571 26.8343 28.9571 26.2485 28.3713L18.9985 21.1212L11.7482 28.3715C11.1624 28.9573 10.2126 28.9573 9.62684 28.3715C9.04105 27.7857 9.04105 26.836 9.62684 26.2502L16.8771 18.9999L9.62684 11.7496Z"
                                    fill=""
                                />
                            </svg>
                        </span>
                    </div>

                    <h4 className="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90 sm:text-title-sm">
                        Brisanje računa
                    </h4>
                    <p className="text-sm leading-6 text-gray-500 dark:text-gray-400 mb-4">
                        Jeste li sigurni da želite obrisati račun{" "}
                        <strong>{invoiceToDelete?.reference_code}</strong>? Ova akcija se ne može poništiti.
                    </p>
                    {invoiceToDelete && (
                        <div className="mb-4 text-sm text-gray-700 dark:text-gray-300">
                            <p><strong>Iznos:</strong> {Number(invoiceToDelete.amount_due || 0).toFixed(2)} EUR</p>
                            <p><strong>Status:</strong> {invoiceToDelete.payment_status}</p>
                        </div>
                    )}
                    <div className="flex items-center justify-center w-full gap-3 mt-7">
                        <Button
                            onClick={() => {
                                deleteInvoiceModal.closeModal();
                                setInvoiceToDelete(null);
                            }}
                            variant="outline"
                            size="sm"
                        >
                            Odustani
                        </Button>
                        <Button
                            onClick={confirmDeleteInvoice}
                            variant="primary"
                            size="sm"
                        >
                            Da, obriši
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default MemberWorkshopInvoices;

