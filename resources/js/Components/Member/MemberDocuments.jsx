import React from "react";
import { Link } from "@inertiajs/react";
import ComponentCard from "@/Components/common/ComponentCard";
import {
    Table,
    TableHeader,
    TableRow,
    TableCell,
    TableBody,
} from "@/Components/ui/table";
import { DocumentTextIcon, EyeIcon } from "@heroicons/react/24/outline";
import axios from "axios";

export default function MemberDocuments({ documents, memberId }) {
    const groupedDocuments = documents.reduce((acc, doc) => {
        const type = doc.document_type;
        if (!acc[type]) {
            acc[type] = [];
        }
        acc[type].push(doc);
        return acc;
    }, {});

    const handleRegenerate = async (doc) => {
        const data = {
            member_id: memberId,
        };

        // Add additional data if it exists (e.g., activity data for privole)
        if (doc.additional_data && doc.additional_data.activity) {
            data.activity = doc.additional_data.activity;
        }

        try {
            const response = await axios.post(
                route("document-templates.generate", doc.template_id),
                data,
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
            console.error('Error regenerating document:', error);
            alert(
                error.response?.data?.message ||
                "Došlo je do pogreške prilikom regeneriranja dokumenta."
            );
        }
    };

    if (!documents || documents.length === 0) {
        return (
            <ComponentCard title="Generirani dokumenti">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Nema generiranih dokumenata za ovog člana.
                </p>
            </ComponentCard>
        );
    }

    return (
        <ComponentCard title="Generirani dokumenti">
            <div className="space-y-6">
                {Object.entries(groupedDocuments).map(([type, docs]) => (
                    <div key={type}>
                        <h4 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            {type === "ispricnica" ? "Ispričnice" : "Privole"}
                        </h4>
                        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
                            <div className="max-w-full overflow-x-auto">
                                <Table>
                                    <TableHeader className="border-b border-gray-200 dark:border-white/[0.05] bg-gray-50">
                                        <TableRow>
                                            <TableCell
                                                isHeader
                                                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                            >
                                                Predložak
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
                                        {docs.map((doc) => (
                                            <TableRow key={doc.id}>
                                                <TableCell className="px-5 py-4 text-start text-theme-sm dark:text-white/90">
                                                    {doc.template_name}
                                                </TableCell>
                                                <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                    {doc.created_at}
                                                </TableCell>
                                                <TableCell className="px-5 py-4 text-start">
                                                    <button
                                                        onClick={() => handleRegenerate(doc)}
                                                        className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400"
                                                        title="Ponovno generiraj dokument"
                                                    >
                                                        <DocumentTextIcon className="h-5 w-5" />
                                                    </button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </ComponentCard>
    );
}
