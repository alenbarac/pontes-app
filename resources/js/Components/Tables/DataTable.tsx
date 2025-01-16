import React, { useState, useEffect } from "react";
import {
    useReactTable,
    getCoreRowModel,
    ColumnDef,
    flexRender,
} from "@tanstack/react-table";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/24/outline";
import { router } from "@inertiajs/react";

interface DataTableProps<T extends object> {
    data: T[];
    columns: ColumnDef<T>[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    pageSizeOptions?: number[];
}

const DataTable = <T extends object>({
    data,
    columns,
    pagination,
    pageSizeOptions = [5, 10, 20, 50],
}: DataTableProps<T>) => {
    const [globalFilter, setGlobalFilter] = useState<string>("");

    useEffect(() => {
        const timeoutId = setTimeout(() => {
            router.get(route("members.index"), {
                page: pagination.current_page,
                per_page: pagination.per_page,
                filter: globalFilter,
            }, { preserveState: true });
        }, 500);

        return () => clearTimeout(timeoutId);
    }, [globalFilter, pagination.current_page, pagination.per_page]);

    const table = useReactTable({
        data,
        columns,
        state: {
            globalFilter,
        },
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: pagination.last_page,
    });

    const handlePageChange = (page: number) => {
        router.get(route("members.index"), {
            page,
            per_page: pagination.per_page,
            filter: globalFilter,
        });
    };

    const handlePageSizeChange = (size: number) => {
        router.get(route("members.index"), {
            page: 1,
            per_page: size,
            filter: globalFilter,
        });
    };

    return (
        <section className="rounded-sm border border-stroke bg-white py-4 shadow-default dark:border-strokedark dark:bg-boxdark">
            {/* Global Search and Page Size Dropdown */}
            <div className="flex justify-between border-b border-stroke px-8 pb-4 dark:border-strokedark">
                {/* Global Search Input */}
                <div className="w-80">
                    <input
                        type="text"
                        value={globalFilter || ""}
                        onChange={(e) => setGlobalFilter(e.target.value)}
                        placeholder="Search..."
                        className="w-full rounded-md border border-stroke px-5 py-2.5 text-sm outline-none focus:border-primary dark:border-strokedark dark:bg-meta-4 dark:focus:border-primary"
                    />
                </div>
                {/* Page Size Dropdown */}
                <div className="flex items-center font-medium text-sm">
                    <select
                        value={pagination.per_page}
                        onChange={(e) =>
                            handlePageSizeChange(Number(e.target.value))
                        }
                        className="bg-transparent pl-2 text-sm border-b border-stroke dark:border-strokedark"
                    >
                        {pageSizeOptions.map((size) => (
                            <option key={size} value={size}>
                                {size}
                            </option>
                        ))}
                    </select>
                    <p className="pl-2 text-black text-sm dark:text-white">
                        Po stranici
                    </p>
                </div>
            </div>

            {/* Table */}
            <table className="w-full table-auto border-collapse px-4 md:px-8">
                <thead className="bg-whiten">
                    {table.getHeaderGroups().map((headerGroup) => (
                        <tr key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <th
                                    key={header.id}
                                    className="px-4 py-2 text-left text-sm font-medium text-black dark:text-white"
                                >
                                    {header.isPlaceholder
                                        ? null
                                        : flexRender(
                                              header.column.columnDef.header,
                                              header.getContext(),
                                          )}
                                </th>
                            ))}
                        </tr>
                    ))}
                </thead>
                <tbody>
                    {table.getRowModel().rows.map((row) => (
                        <tr key={row.id} className="hover:bg-gray-200">
                            {row.getVisibleCells().map((cell) => (
                                <td
                                    key={cell.id}
                                    className="px-4 py-2 text-sm text-meta-4 dark:text-gray-300 border-b border-stroke dark:border-strokedark"
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

            {/* Pagination */}
            <div className="flex flex-col md:flex-row items-center justify-between border-t border-stroke px-8 pt-5 dark:border-strokedark">
                <p className="font-medium text-sm">
                    Stranica {pagination.current_page} od {pagination.last_page}
                </p>
                <div className="flex items-center gap-1 mt-2 md:mt-0">
                    {/* Previous Page */}
                    <button
                        onClick={() =>
                            handlePageChange(pagination.current_page - 1)
                        }
                        disabled={pagination.current_page === 1}
                        className="px-2 py-1 rounded-md bg-gray-200"
                    >
                        <ChevronLeftIcon className="h-4 w-4" />
                    </button>

                    {/* Page Numbers */}
                    {[...Array(pagination.last_page)].map((_, i) => (
                        <button
                            key={i}
                            onClick={() => handlePageChange(i + 1)}
                            className={`px-2 py-1 rounded-md ${
                                pagination.current_page === i + 1
                                    ? "bg-primary text-white"
                                    : "bg-gray-200 text-gray-700 hover:bg-gray-300"
                            }`}
                        >
                            {i + 1}
                        </button>
                    ))}

                    {/* Next Page */}
                    <button
                        onClick={() =>
                            handlePageChange(pagination.current_page + 1)
                        }
                        disabled={
                            pagination.current_page === pagination.last_page
                        }
                        className="px-2 py-1 rounded-md bg-gray-200"
                    >
                        <ChevronRightIcon className="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>
    );
};

export default DataTable;
