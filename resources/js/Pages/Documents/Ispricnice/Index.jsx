import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import {
    Table,
    TableHeader,
    TableRow,
    TableCell,
    TableBody,
} from "@/Components/ui/table";
import {
    PencilIcon,
    TrashIcon,
    EyeIcon,
} from "@heroicons/react/24/outline";
import { router } from "@inertiajs/react";
import toast from "react-hot-toast";
import Button from "@/Components/ui/button/Button";

export default function Index({ templates, type }) {
    const handleDelete = (template) => {
        if (confirm("Jeste li sigurni da želite izbrisati ovaj predložak?")) {
            router.delete(route("document-templates.destroy", template.id), {
                onSuccess: () => {
                    toast.success("Predložak uspješno obrisan.");
                },
                onError: () => {
                    toast.error("Došlo je do pogreške prilikom brisanja.");
                },
            });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Ispričnice" />
            <Breadcrumb pageName="Ispričnice" />
            <ComponentCard
                title="Predlošci ispričnica"
                headerAction={
                    <Link
                        href={route("document-templates.ispricnice.create")}
                        className="inline-flex items-center justify-center gap-2 rounded-lg transition px-4 py-3 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
                    >
                        Novi predložak
                    </Link>
                }
            >
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
                    <div className="max-w-full overflow-x-auto">
                        <Table>
                            <TableHeader className="border-b border-gray-200 dark:border-white/[0.05] bg-gray-50">
                                <TableRow>
                                    <TableCell
                                        isHeader
                                        className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                    >
                                        Naziv
                                    </TableCell>
                                    <TableCell
                                        isHeader
                                        className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                    >
                                        Opis
                                    </TableCell>
                                    <TableCell
                                        isHeader
                                        className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                    >
                                        Status
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
                                {templates.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="px-5 py-8 text-center text-gray-500 dark:text-gray-400"
                                        >
                                            Nema predložaka. Kreirajte novi predložak.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    templates.map((template) => (
                                        <TableRow key={template.id}>
                                            <TableCell className="px-5 py-4 text-start text-theme-sm dark:text-white/90 font-medium">
                                                {template.name}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                {template.description || "—"}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-start">
                                                <span
                                                    className={`px-2 py-1 text-xs rounded ${
                                                        template.is_active
                                                            ? "bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400"
                                                            : "bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400"
                                                    }`}
                                                >
                                                    {template.is_active
                                                        ? "Aktivan"
                                                        : "Neaktivan"}
                                                </span>
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-start">
                                                <div className="flex gap-2">
                                                    <Link
                                                        href={route(
                                                            "document-templates.show",
                                                            template.id
                                                        )}
                                                        className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400"
                                                        title="Uredi predložak"
                                                    >
                                                        <PencilIcon className="h-5 w-5" />
                                                    </Link>
                                                    <button
                                                        onClick={() =>
                                                            handleDelete(template)
                                                        }
                                                        className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                                        title="Izbriši predložak"
                                                    >
                                                        <TrashIcon className="h-5 w-5" />
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </ComponentCard>
        </AuthenticatedLayout>
    );
}
