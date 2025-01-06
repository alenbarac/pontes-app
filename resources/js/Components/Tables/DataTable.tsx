import React, { useMemo, useState } from "react";
import {
    useReactTable,
    getCoreRowModel,
    getPaginationRowModel,
    getFilteredRowModel,
    ColumnDef,
    flexRender,
} from "@tanstack/react-table";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/24/outline";

interface DataTableProps<T extends object> {
    data: T[];
    columns: ColumnDef<T>[];
    pageSizeOptions?: number[];
    defaultPageSize?: number;
}

const DataTable = <T extends object>({
    data,
    columns,
    pageSizeOptions = [5, 10, 20, 50],
    defaultPageSize = 10,
}: DataTableProps<T>) => {
    const [globalFilter, setGlobalFilter] = useState<string>("");

    const table = useReactTable({
        data,
        columns,
        state: {
            globalFilter,
        },
        onGlobalFilterChange: setGlobalFilter,
        globalFilterFn: (row, columnId, filterValue) => {
            if (!filterValue) return true;
            const value = row.getValue(columnId);
            return String(value)
                .toLowerCase()
                .includes(String(filterValue).toLowerCase());
        },
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        initialState: {
            pagination: { pageSize: defaultPageSize },
        },
    });

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
                        placeholder="Pretraga..."
                        className="w-full rounded-md border border-stroke px-5 py-2.5 text-sm outline-none focus:border-primary dark:border-strokedark dark:bg-meta-4 dark:focus:border-primary"
                    />
                </div>
                {/* Page Size Dropdown */}
                <div className="flex items-center font-medium text-sm">
                    <select
                        value={table.getState().pagination.pageSize}
                        onChange={(e) =>
                            table.setPageSize(Number(e.target.value))
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
                <thead>
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
                    {table.getRowModel().rows.map((row, rowIndex) => (
                        <tr key={row.id} className="hover:bg-gray-200">
                            {row.getVisibleCells().map((cell) => (
                                <td
                                    key={cell.id}
                                    className={`px-4 py-2 text-sm text-gray-900 dark:text-gray-300 border-b border-stroke dark:border-strokedark ${
                                        rowIndex ===
                                        table.getRowModel().rows.length - 1
                                            ? "border-b-0"
                                            : ""
                                    }`}
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
            <div className="flex justify-between border-t border-stroke px-8 pt-5 dark:border-strokedark">
                <p className="font-medium text-sm">
                    Stranica {table.getState().pagination.pageIndex + 1} od{" "}
                    {table.getPageCount()}
                </p>
                <div className="flex space-x-2">
                    {/* Previous Page Button */}
                    <button
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                        className={`flex items-center justify-center px-2 py-1 rounded-md ${
                            table.getCanPreviousPage()
                                ? "bg-primary text-white hover:bg-primary-dark"
                                : "bg-gray-300 text-gray-500 cursor-not-allowed"
                        }`}
                    >
                        <ChevronLeftIcon className="h-4 w-4" />
                    </button>

                    {/* Next Page Button */}
                    <button
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                        className={`flex items-center justify-center px-2 py-1 rounded-md ${
                            table.getCanNextPage()
                                ? "bg-primary text-white hover:bg-primary-dark"
                                : "bg-gray-300 text-gray-500 cursor-not-allowed"
                        }`}
                    >
                        <ChevronRightIcon className="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>
    );
};

export default DataTable;
