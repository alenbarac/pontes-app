import React, { useState, useEffect, useMemo } from "react";
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
} from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import InvoiceActionsDropdown from "./InvoiceActionColumn";

const InvoicesDataTable = ({
    data,
    pagination,
    pageSizeOptions = [10, 20, 50],
}) => {
    const [globalFilter, setGlobalFilter] = useState("");

    const columns = useMemo(
        () => [
            {
                accessorKey: "reference_code",
                header: "Referenca",
            },
            {
                accessorKey: "member",
                header: "Član",
                cell: ({ row }) => {
                    const m = row.original.member;
                    return `${m.first_name} ${m.last_name}`;
                },
            },
            {
                accessorKey: "workshop",
                header: "Radionica",
                cell: ({ row }) => row.original.workshop?.name || "-",
            },
            {
                accessorKey: "due_date",
                header: "Datum dospijeća",
                cell: ({ row }) =>
                    new Date(row.original.due_date).toLocaleDateString("hr-HR"),
            },
            {
                accessorKey: "amount_due",
                header: "Iznos",
                cell: ({ row }) => `${row.original.amount_due} €`,
            },
            {
                accessorKey: "payment_status",
                header: "Status",
                cell: ({ row }) => {
                    const status = row.original.payment_status;
                    return (
                        <span
                            className={`inline-block px-2 py-1 text-xs rounded ${
                                status === "Plaćeno"
                                    ? "bg-green-100 text-green-800"
                                    : status === "Opomeni"
                                      ? "bg-red-100 text-red-800"
                                      : "bg-yellow-100 text-yellow-800"
                            }`}
                        >
                            {status}
                        </span>
                    );
                },
            },
            {
                accessorKey: "actions",
                header: "",
                cell: ({ row }) => (
                    <InvoiceActionsDropdown
                        invoiceId={row.original.id}
                        isPaid={row.original.payment_status === "Plaćeno"}
                    />
                ),
            },
        ],
        [],
    );

    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get(
                route("invoices.index"),
                {
                    page: pagination.current_page,
                    per_page: pagination.per_page,
                    filter: globalFilter,
                },
                { preserveState: true },
            );
        }, 500);

        return () => clearTimeout(timeout);
    }, [globalFilter, pagination.current_page, pagination.per_page]);

    const table = useReactTable({
        data,
        columns,
        state: { globalFilter },
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: pagination.last_page,
    });

    const handlePageChange = (page) => {
        router.get(route("invoices.index"), {
            page,
            per_page: pagination.per_page,
            filter: globalFilter,
        });
    };

    const handlePageSizeChange = (size) => {
        router.get(route("invoices.index"), {
            page: 1,
            per_page: size,
            filter: globalFilter,
        });
    };

    return (
        <div className="overflow-hidden rounded-xl bg-white dark:bg-white/[0.03]">
            {/* Table header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-4 border border-b-0 border-gray-100 dark:border-white/[0.05] rounded-t-xl">
                <div className="flex items-center gap-3">
                    <span className="text-gray-500 dark:text-gray-400">
                        Prikaz
                    </span>
                    <select
                        value={pagination.per_page}
                        onChange={(e) =>
                            handlePageSizeChange(Number(e.target.value))
                        }
                        className="h-9 py-2 pl-3 pr-8 text-sm border rounded-lg"
                    >
                        {pageSizeOptions.map((size) => (
                            <option key={size} value={size}>
                                {size}
                            </option>
                        ))}
                    </select>
                    <span className="text-gray-500 dark:text-gray-400">
                        računa
                    </span>
                </div>
                <input
                    type="text"
                    value={globalFilter}
                    onChange={(e) => setGlobalFilter(e.target.value)}
                    placeholder="Pretraga..."
                    className="h-11 px-4 py-2 rounded-lg border w-full sm:w-[300px] mt-3 sm:mt-0"
                />
            </div>

            {/* Table */}
            <div className="max-w-full overflow-x-auto">
                <table className="w-full table-auto border-collapse">
                    <thead>
                        {table.getHeaderGroups().map((hg) => (
                            <tr key={hg.id}>
                                {hg.headers.map((header) => (
                                    <th
                                        key={header.id}
                                        className="px-4 py-3 border text-left text-sm font-medium bg-gray-50"
                                    >
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef
                                                      .header,
                                                  header.getContext(),
                                              )}
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows.map((row) => (
                            <tr key={row.id} className="hover:bg-gray-100">
                                {row.getVisibleCells().map((cell) => (
                                    <td
                                        key={cell.id}
                                        className="px-4 py-4 border text-sm whitespace-nowrap"
                                    >
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-end px-4 py-3 space-x-2">
                <button
                    onClick={() =>
                        handlePageChange(pagination.current_page - 1)
                    }
                    disabled={pagination.current_page === 1}
                    className="px-3 py-1 border rounded"
                >
                    &laquo;
                </button>
                <span className="text-sm">
                    Stranica {pagination.current_page} od {pagination.last_page}
                </span>
                <button
                    onClick={() =>
                        handlePageChange(pagination.current_page + 1)
                    }
                    disabled={pagination.current_page === pagination.last_page}
                    className="px-3 py-1 border rounded"
                >
                    &raquo;
                </button>
            </div>
        </div>
    );
};

export default InvoicesDataTable;
