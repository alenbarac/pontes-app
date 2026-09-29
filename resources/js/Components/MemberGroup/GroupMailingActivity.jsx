import { Link } from "@inertiajs/react";
import { Table, TableHeader, TableBody, TableRow, TableCell } from "@/Components/ui/table";

const columns = ["Datum", "Sadržaj", "Primatelji", "Status"];

export default function GroupMailingActivity({ mailings = [] }) {
    const rows = Array.isArray(mailings) ? mailings : [];

    return (
        <div className="mt-3 border-t border-gray-200 pt-3 dark:border-gray-800">
            <p className="mb-2 text-xs text-gray-500 dark:text-gray-400">Email Radnje</p>

            {rows.length === 0 ? (
                <p className="text-sm text-gray-800 dark:text-white/90">Još nema slanja za ovu grupu.</p>
            ) : (
                <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
                    <div className="max-w-full overflow-x-auto">
                        <Table>
                            <TableHeader className="border-b border-gray-200 bg-gray-50 dark:border-white/[0.05]">
                                <TableRow>
                                    {columns.map((heading) => (
                                        <TableCell
                                            key={heading}
                                            isHeader
                                            className="px-4 py-2.5 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                                        >
                                            {heading}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.id} className="border-b border-gray-100 last:border-0 dark:border-white/[0.05]">
                                        <TableCell className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {row.started_at}
                                        </TableCell>
                                        <TableCell className="px-4 py-3 text-sm">
                                            <Link href={row.url} className="font-medium text-brand-600 hover:text-brand-700">
                                                {row.label}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {row.total}
                                        </TableCell>
                                        <TableCell className="px-4 py-3 text-sm text-gray-800 dark:text-white/90">
                                            {row.status_label}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            )}
        </div>
    );
}
