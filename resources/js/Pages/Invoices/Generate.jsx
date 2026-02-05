import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";
import { Modal } from "@/Components/ui/modal";
import { useModal } from "@/hooks/useModal";
import toast from "react-hot-toast";
import axios from "axios";

export default function Generate({ 
    currentMonth, 
    currentSchoolYear, 
    monthStatuses 
}) {
    const [selectedMonth, setSelectedMonth] = useState(currentMonth);
    const [previewData, setPreviewData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [deletingMonth, setDeletingMonth] = useState(null);
    const deleteModal = useModal();
    const generateModal = useModal();

    const handlePreview = () => {
        if (!selectedMonth) {
            toast.error("Molimo odaberite mjesec.");
            return;
        }

        setLoading(true);
        axios
            .post(route("invoices.generate.preview"), {
                target_month: selectedMonth,
            })
            .then((response) => {
                setPreviewData(response.data);
                setLoading(false);
            })
            .catch((error) => {
                console.error(error);
                const errorMessage =
                    error.response?.data?.message ||
                    error.response?.data?.error ||
                    error.message ||
                    "Greška pri učitavanju pregleda.";
                toast.error(errorMessage);
                setLoading(false);
            });
    };

    const handleDeleteMonth = (month) => {
        setDeletingMonth(month);
        deleteModal.openModal();
    };

    const confirmDeleteMonth = () => {
        if (!deletingMonth) return;

        const monthLabel = monthStatuses.find(s => s.month === deletingMonth)?.label || deletingMonth;
        axios
            .delete("/invoices/generate/month", {
                data: { target_month: deletingMonth },
            })
            .then(() => {
                setDeletingMonth(null);
                deleteModal.closeModal();
                toast.success("Računi uspješno obrisani.");
                // Reload to refresh month statuses
                router.reload({ only: ["monthStatuses"] });
            })
            .catch((error) => {
                setDeletingMonth(null);
                deleteModal.closeModal();
                const errorMessage =
                    error.response?.data?.message ||
                    error.response?.data?.error ||
                    error.message ||
                    "Došlo je do greške pri brisanju računa.";
                toast.error(errorMessage);
            });
    };

    const handleGenerate = () => {
        if (!selectedMonth) {
            toast.error("Molimo odaberite mjesec.");
            return;
        }

        if (!previewData || previewData.summary.ready_to_generate === 0) {
            toast.error("Nema računa za generiranje za odabrani mjesec.");
            return;
        }

        generateModal.openModal();
    };

    const confirmGenerate = () => {
        if (!selectedMonth || !previewData) {
            return;
        }

        setGenerating(true);
        router.post(
            route("invoices.generate"),
            { target_month: selectedMonth },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    setGenerating(false);
                    setPreviewData(null);
                    generateModal.closeModal();
                    toast.success(`Uspješno generirano ${previewData.summary.ready_to_generate} računa.`);
                    // Reload to refresh month statuses
                    router.reload({ only: ["monthStatuses"] });
                },
                onError: (errors) => {
                    setGenerating(false);
                    generateModal.closeModal();
                    if (errors.target_month) {
                        toast.error(errors.target_month);
                    } else if (errors.generation) {
                        toast.error(errors.generation);
                    } else {
                        toast.error("Došlo je do greške pri generiranju računa.");
                    }
                },
            }
        );
    };


    return (
        <AuthenticatedLayout>
            <Head title="Generiranje računa" />
            <Breadcrumb pageName="Generiranje računa" />
            
            <div className="space-y-6">
                <ComponentCard title="Generiranje računa">
                    <div className="space-y-6">
                        {/* School Year Info */}
                        <div className="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <h3 className="font-semibold text-gray-800 dark:text-white mb-2">
                                Trenutna školska godina: {currentSchoolYear.label}
                            </h3>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Od {currentSchoolYear.start} do {currentSchoolYear.end}
                            </p>
                        </div>

                        {/* Month Selection */}
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Odaberi mjesec za generiranje
                                </label>
                                <select
                                    value={selectedMonth}
                                    onChange={(e) => {
                                        setSelectedMonth(e.target.value);
                                        setPreviewData(null);
                                    }}
                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-2 text-sm text-gray-900 dark:text-white focus:border-brand-500 focus:ring-brand-500"
                                >
                                    {monthStatuses.map((status) => (
                                        <option key={status.month} value={status.month}>
                                            {status.label} {status.is_current && "(Trenutni)"}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex gap-4">
                                <Button
                                    onClick={handlePreview}
                                    disabled={loading || !selectedMonth}
                                    variant="outline"
                                >
                                    {loading ? "Učitavanje..." : "Pregled"}
                                </Button>
                                <Button
                                    onClick={handleGenerate}
                                    disabled={generating || !previewData || previewData.summary.ready_to_generate === 0}
                                    variant="primary"
                                >
                                    {generating ? "Generiranje..." : "Generiraj račune"}
                                </Button>
                            </div>
                        </div>

                        {/* Preview Data */}
                        {previewData && (
                            <div className="mt-6 space-y-4">
                                <div className="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <h3 className="font-semibold text-gray-800 dark:text-white mb-4">
                                        Pregled za {previewData.target_month_label}
                                    </h3>
                                    
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                        <div className="p-3 bg-white dark:bg-gray-900 rounded-lg">
                                            <p className="text-xs text-gray-600 dark:text-gray-400">Ukupno</p>
                                            <p className="text-xl font-bold text-gray-900 dark:text-white">
                                                {previewData.summary.total}
                                            </p>
                                        </div>
                                        <div className="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                            <p className="text-xs text-gray-600 dark:text-gray-400">Spremno za generiranje</p>
                                            <p className="text-xl font-bold text-green-600 dark:text-green-400">
                                                {previewData.summary.ready_to_generate}
                                            </p>
                                        </div>
                                        <div className="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                            <p className="text-xs text-gray-600 dark:text-gray-400">Već postoji</p>
                                            <p className="text-xl font-bold text-yellow-600 dark:text-yellow-400">
                                                {previewData.summary.already_exists}
                                            </p>
                                        </div>
                                        <div className="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                            <p className="text-xs text-gray-600 dark:text-gray-400">Preskočeno</p>
                                            <p className="text-xl font-bold text-gray-600 dark:text-gray-400">
                                                {previewData.summary.skipped}
                                            </p>
                                        </div>
                                    </div>

                                    {previewData.previews && previewData.previews.length > 0 && (
                                        <div className="mt-4 max-h-96 overflow-y-auto">
                                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0">
                                                    <tr>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Član
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Radionica
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Plan
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Iznos
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Status
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    {previewData.previews.map((preview, index) => (
                                                        <tr key={index}>
                                                            <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                {preview.member_name}
                                                            </td>
                                                            <td className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                                                {preview.workshop_name}
                                                            </td>
                                                            <td className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                                                {preview.membership_plan}
                                                            </td>
                                                            <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                {typeof preview.amount === 'number' 
                                                                    ? preview.amount.toFixed(2) 
                                                                    : parseFloat(preview.amount || 0).toFixed(2)} €
                                                            </td>
                                                            <td className="px-4 py-2 text-sm">
                                                                {preview.already_exists ? (
                                                                    <span className="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 rounded">
                                                                        Već postoji
                                                                    </span>
                                                                ) : preview.should_generate ? (
                                                                    <span className="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 rounded">
                                                                        Spremno
                                                                    </span>
                                                                ) : (
                                                                    <span className="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-400 rounded">
                                                                        Preskočeno
                                                                    </span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </ComponentCard>

                {/* Month Status Overview */}
                <ComponentCard title="Pregled mjeseci u školskoj godini">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {monthStatuses.map((status) => (
                            <div
                                key={status.month}
                                className={`p-4 rounded-lg border ${
                                    status.is_current
                                        ? "bg-brand-50 dark:bg-brand-900/20 border-brand-500"
                                        : status.is_past
                                        ? "bg-gray-50 dark:bg-gray-800 border-gray-300 dark:border-gray-700"
                                        : "bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700"
                                }`}
                            >
                                <div className="flex items-center justify-between mb-2">
                                    <h4 className="font-medium text-gray-900 dark:text-white">
                                        {status.label}
                                    </h4>
                                    <div className="flex items-center gap-2">
                                        {status.is_current && (
                                            <span className="px-2 py-1 text-xs bg-brand-500 text-white rounded">
                                                Trenutni
                                            </span>
                                        )}
                                        {status.invoice_count > 0 && (
                                            <button
                                                onClick={() => handleDeleteMonth(status.month)}
                                                disabled={deletingMonth === status.month}
                                                className="px-2 py-1 text-xs bg-red-500 hover:bg-red-600 disabled:bg-red-300 text-white rounded transition-colors"
                                                title="Obriši sve račune za ovaj mjesec"
                                            >
                                                {deletingMonth === status.month ? "Brisanje..." : "Brisanje"}
                                            </button>
                                        )}
                                    </div>
                                </div>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {status.invoice_count} računa
                                </p>
                            </div>
                        ))}
                    </div>
                </ComponentCard>

                {/* Delete Confirmation Modal */}
                <Modal
                    isOpen={deleteModal.isOpen}
                    onClose={() => {
                        deleteModal.closeModal();
                        setDeletingMonth(null);
                    }}
                    className="max-w-md p-6"
                >
                    <h4 className="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                        Brisanje računa
                    </h4>
                    <p className="mb-6 text-gray-600 dark:text-gray-400">
                        Jeste li sigurni da želite obrisati sve račune za{" "}
                        <strong>
                            {deletingMonth
                                ? monthStatuses.find((s) => s.month === deletingMonth)?.label ||
                                  deletingMonth
                                : ""}
                        </strong>
                        ? Ova akcija se ne može poništiti.
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button variant="primary" onClick={confirmDeleteMonth}>
                            Da, obriši
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                deleteModal.closeModal();
                                setDeletingMonth(null);
                            }}
                        >
                            Odustani
                        </Button>
                    </div>
                </Modal>

                {/* Generate Confirmation Modal */}
                <Modal
                    isOpen={generateModal.isOpen}
                    onClose={() => {
                        generateModal.closeModal();
                    }}
                    className="max-w-md p-6"
                >
                    <h4 className="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                        Generiranje računa
                    </h4>
                    <p className="mb-6 text-gray-600 dark:text-gray-400">
                        Jeste li sigurni da želite generirati{" "}
                        <strong>
                            {previewData?.summary?.ready_to_generate || 0} računa
                        </strong>{" "}
                        za <strong>{previewData?.target_month_label || selectedMonth}</strong>?
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button 
                            variant="primary" 
                            onClick={confirmGenerate}
                            disabled={generating}
                        >
                            {generating ? "Generiranje..." : "Da, generiraj"}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                generateModal.closeModal();
                            }}
                            disabled={generating}
                        >
                            Odustani
                        </Button>
                    </div>
                </Modal>
            </div>
        </AuthenticatedLayout>
    );
}
