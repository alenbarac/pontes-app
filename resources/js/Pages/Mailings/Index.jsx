import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { CheckIcon, TrashIcon } from "@heroicons/react/24/outline";
import toast from "react-hot-toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";
import { Modal } from "@/Components/ui/modal";
import { Table, TableHeader, TableBody, TableRow, TableCell } from "@/Components/ui/table";

const actionClass =
    "p-1.5 rounded text-gray-600 hover:bg-gray-100 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-gray-600 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-brand-400";

export default function Index({ mailings }) {
    const rows = mailings?.data ?? [];
    const [pending, setPending] = useState(null);
    const [working, setWorking] = useState(false);

    const goToPage = (page) => {
        router.get(route("mailings.index"), { page }, { preserveState: true, preserveScroll: true });
    };

    const confirmPending = () => {
        if (!pending) {
            return;
        }

        const isDelete = pending.action === "delete";
        setWorking(true);
        const visit = {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash ?? {};
                if (flash.error) {
                    toast.error(flash.error);
                    return;
                }
                if (flash.success) {
                    toast.success(flash.success);
                }
                setPending(null);
            },
            onError: () => {
                toast.error(isDelete ? "Slanje nije obrisano." : "Slanje nije zatvoreno.");
            },
            onFinish: () => setWorking(false),
        };

        if (isDelete) {
            router.delete(route("mailings.destroy", pending.row.id), visit);
            return;
        }

        router.post(route("mailings.close", pending.row.id), {}, visit);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Evidencija slanja" />
            <Breadcrumb pageName="Evidencija slanja" />
            <ComponentCard title="Evidencija slanja">
                {rows.length === 0 ? (
                    <p className="text-sm text-gray-500 dark:text-gray-400">Još nema slanja.</p>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
                        <div className="max-w-full overflow-x-auto">
                            <Table>
                                <TableHeader className="border-b border-gray-200 bg-gray-50 dark:border-white/[0.05]">
                                    <TableRow>
                                        {["Datum", "Sadržaj", "Grupa", "Primatelji", "Status", "Akcije"].map((heading) => (
                                            <TableCell
                                                key={heading}
                                                isHeader
                                                className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                                            >
                                                {heading}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => (
                                        <TableRow key={row.id} className="border-b border-gray-100 dark:border-white/[0.05]">
                                            <TableCell className="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                {row.started_at}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-sm">
                                                <Link href={row.url} className="font-medium text-brand-600 hover:text-brand-700">
                                                    {row.label}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                {row.group_name}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                {row.total}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-sm text-gray-800 dark:text-white/90">
                                                {row.status_label}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-start">
                                                <div className="flex gap-1">
                                                    <button
                                                        type="button"
                                                        className={actionClass}
                                                        title={row.queued > 0 ? "Označi kao završeno" : "Nema poruka u redu"}
                                                        disabled={row.queued === 0 || row.is_closed}
                                                        onClick={() => setPending({ action: "close", row })}
                                                    >
                                                        <CheckIcon className="h-5 w-5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className={`${actionClass} hover:text-red-600 dark:hover:text-red-400`}
                                                        title="Obriši zapis"
                                                        onClick={() => setPending({ action: "delete", row })}
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
                )}

                {mailings.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => goToPage(mailings.current_page - 1)}
                            disabled={mailings.current_page === 1}
                            className="rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 disabled:opacity-40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        >
                            &laquo;
                        </button>
                        <span className="text-sm text-gray-700 dark:text-gray-300">
                            Stranica {mailings.current_page} od {mailings.last_page}
                        </span>
                        <button
                            type="button"
                            onClick={() => goToPage(mailings.current_page + 1)}
                            disabled={mailings.current_page === mailings.last_page}
                            className="rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 disabled:opacity-40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        >
                            &raquo;
                        </button>
                    </div>
                )}
            </ComponentCard>

            <Modal isOpen={pending !== null} onClose={() => (working ? null : setPending(null))} className="max-w-md m-4">
                <div className="w-full max-w-md rounded-3xl bg-white p-6 dark:bg-gray-900">
                    <h5 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white">
                        {pending?.action === "delete" ? "Obrisati slanje?" : "Označiti kao završeno?"}
                    </h5>
                    <p className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                        {pending?.action === "delete"
                            ? `${pending.row.label} i popis primatelja uklanjaju se iz evidencije. Poslane poruke se time ne povlače.`
                            : `${pending?.row.label}: poruke koje su još u redu neće se poslati. Već poslane ostaju poslane.`}
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" disabled={working} onClick={() => setPending(null)}>
                            Odustani
                        </Button>
                        <Button type="button" variant="primary" size="sm" disabled={working} onClick={confirmPending}>
                            {working ? "Spremanje..." : pending?.action === "delete" ? "Obriši" : "Završi"}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
