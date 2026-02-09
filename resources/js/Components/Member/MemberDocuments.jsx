import React, { useState } from "react";
import { Link, router } from "@inertiajs/react";
import {
    Table,
    TableHeader,
    TableRow,
    TableCell,
    TableBody,
} from "@/Components/ui/table";
import { DocumentTextIcon, TrashIcon } from "@heroicons/react/24/outline";
import axios from "axios";
import toast from "react-hot-toast";
import { Modal } from "@/Components/ui/modal";
import { useModal } from "@/hooks/useModal";
import Button from "@/Components/ui/button/Button";

export default function MemberDocuments({ documents, memberId }) {
    const deleteModal = useModal();
    const [documentToDelete, setDocumentToDelete] = useState(null);

    // Sort documents by created_at descending (newest first)
    const sortedDocuments = [...documents].sort((a, b) => {
        return new Date(b.created_at) - new Date(a.created_at);
    });

    const handleView = async (doc) => {
        try {
            const response = await axios.get(
                route("member-documents.download", doc.id),
                {
                    responseType: 'blob',
                }
            );

            // Create blob and open in new tab
            const blob = new Blob([response.data], { type: 'application/pdf' });
            const url = window.URL.createObjectURL(blob);
            window.open(url, '_blank');
            // Clean up after a delay to allow the browser to load it
            setTimeout(() => window.URL.revokeObjectURL(url), 100);
        } catch (error) {
            console.error('Error viewing document:', error);
            toast.error(
                error.response?.data?.message ||
                "Došlo je do pogreške prilikom otvaranja dokumenta."
            );
        }
    };

    const handleDelete = (doc) => {
        setDocumentToDelete(doc);
        deleteModal.openModal();
    };

    const confirmDelete = () => {
        if (!documentToDelete) return;

        router.delete(route("member-documents.destroy", documentToDelete.id), {
            onSuccess: () => {
                deleteModal.closeModal();
                setDocumentToDelete(null);
                toast.success("Dokument uspješno obrisan.");
            },
            onError: () => {
                deleteModal.closeModal();
                setDocumentToDelete(null);
                toast.error("Došlo je do pogreške prilikom brisanja dokumenta.");
            },
        });
    };

    if (!documents || documents.length === 0) {
        return (
            <div className="p-5 border border-gray-200 rounded-2xl dark:border-gray-800 lg:p-6">
                <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">
                    Generirani dokumenti
                </h4>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Nema generiranih dokumenata za ovog člana.
                </p>
            </div>
        );
    }

    return (
        <div className="p-5 border border-gray-200 rounded-2xl dark:border-gray-800 lg:p-6">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90 mb-6">
                Generirani dokumenti
            </h4>
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
                <div className="max-w-full overflow-x-auto">
                    <Table>
                        <TableHeader className="border-b border-gray-200 dark:border-white/[0.05] bg-gray-50 dark:bg-gray-800/50">
                            <TableRow>
                                <TableCell
                                    isHeader
                                    className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                >
                                    Naziv dokumenta
                                </TableCell>
                                <TableCell
                                    isHeader
                                    className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                >
                                    Tip
                                </TableCell>
                                <TableCell
                                    isHeader
                                    className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                >
                                    Datum generiranja
                                </TableCell>
                                <TableCell
                                    isHeader
                                    className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                >
                                    Akcije
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                            {sortedDocuments.map((doc) => (
                                <TableRow key={doc.id}>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm dark:text-white/90">
                                        {doc.template_name}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                        <span className="inline-block px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                            {doc.document_type === "ispricnica" ? "Ispričnica" : "Privola"}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                        {doc.created_at}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start">
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => handleView(doc)}
                                                className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
                                                title="Pregledaj PDF"
                                            >
                                                <DocumentTextIcon className="h-5 w-5" />
                                            </button>
                                            <button
                                                onClick={() => handleDelete(doc)}
                                                className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors"
                                                title="Obriši dokument"
                                            >
                                                <TrashIcon className="h-5 w-5" />
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Delete Confirmation Modal */}
            <Modal
                isOpen={deleteModal.isOpen}
                onClose={() => {
                    deleteModal.closeModal();
                    setDocumentToDelete(null);
                }}
                className="max-w-[600px] p-5 lg:p-10"
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
                        Brisanje dokumenta
                    </h4>
                    <p className="text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Jeste li sigurni da želite obrisati dokument{" "}
                        <strong>{documentToDelete?.template_name}</strong>? Ova akcija se ne može poništiti.
                    </p>

                    <div className="flex items-center justify-center w-full gap-3 mt-7">
                        <Button
                            onClick={() => {
                                deleteModal.closeModal();
                                setDocumentToDelete(null);
                            }}
                            variant="outline"
                            size="sm"
                        >
                            Odustani
                        </Button>
                        <Button
                            onClick={confirmDelete}
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
}
