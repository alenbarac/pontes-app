import { Head, Link, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import { Table, TableHeader, TableBody, TableRow, TableCell } from "@/Components/ui/table";

export default function Index({ mailings }) {
    const rows = mailings?.data ?? [];

    const goToPage = (page) => {
        router.get(route("mailings.index"), { page }, { preserveState: true, preserveScroll: true });
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
                                        {["Datum", "Sadržaj", "Grupa", "Primatelji", "Status"].map((heading) => (
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
        </AuthenticatedLayout>
    );
}
