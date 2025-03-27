import React, { useState, useEffect } from "react";
import {
    useReactTable,
    getCoreRowModel,
    ColumnDef,
    flexRender,
} from "@tanstack/react-table";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/24/outline";
import { router } from "@inertiajs/react";

interface MembersDataTableProps<T extends object> {
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

const MembersDataTable = <T extends object>({
    data,
    columns,
    pagination,
    pageSizeOptions = [5, 10, 20, 50],
}: MembersDataTableProps<T>) => {
    const [globalFilter, setGlobalFilter] = useState<string>("");

    useEffect(() => {
        const timeoutId = setTimeout(() => {
            router.get(
                route("members.index"),
                {
                    page: pagination.current_page,
                    per_page: pagination.per_page,
                    filter: globalFilter,
                },
                { preserveState: true },
            );
        }, 500);

        return () => clearTimeout(timeoutId);
    }, [globalFilter, pagination.current_page, pagination.per_page]);

    const table = useReactTable({
        data,
        columns,
        state: { globalFilter },
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

    // Calculate the indexes for "Showing entries"
    const startIndex = (pagination.current_page - 1) * pagination.per_page;
    const endIndex = Math.min(
        pagination.current_page * pagination.per_page,
        pagination.total,
    );
    const totalEntries = pagination.total;

    return (
        <div className="overflow-hidden rounded-xl bg-white dark:bg-white/[0.03]">
            {/* Header */}
            <div className="flex flex-col gap-2 px-4 py-4 border border-b-0 border-gray-100 dark:border-white/[0.05] rounded-t-xl sm:flex-row sm:items-center sm:justify-between">
                {/* Left side: Page Size Dropdown */}
                <div className="flex items-center gap-3">
                    <span className="text-gray-500 dark:text-gray-400">
                        Prikaz
                    </span>
                    <div className="relative z-20 bg-transparent">
                        <select
                            value={pagination.per_page}
                            onChange={(e) =>
                                handlePageSizeChange(Number(e.target.value))
                            }
                            className="w-full py-2 pl-3 pr-8 text-sm text-gray-800 bg-transparent border border-gray-300 rounded-lg appearance-none dark:bg-dark-900 h-9 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        >
                            {pageSizeOptions.map((size) => (
                                <option
                                    key={size}
                                    value={size}
                                    className="text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                                >
                                    {size}
                                </option>
                            ))}
                        </select>
                        <span className="absolute z-30 text-gray-500 -translate-y-1/2 right-2 top-1/2 dark:text-gray-400">
                            <svg
                                className="stroke-current"
                                width="16"
                                height="16"
                                viewBox="0 0 16 16"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M3.8335 5.9165L8.00016 10.0832L12.1668 5.9165"
                                    strokeWidth="1.2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </span>
                    </div>
                    <span className="text-gray-500 dark:text-gray-400">
                        članova
                    </span>
                </div>

                {/* Right side: Global Search */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative">
                        <button className="absolute text-gray-500 -translate-y-1/2 left-4 top-1/2 dark:text-gray-400">
                            <svg
                                className="fill-current"
                                width="20"
                                height="20"
                                viewBox="0 0 20 20"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z"
                                />
                            </svg>
                        </button>
                        <input
                            type="text"
                            value={globalFilter}
                            onChange={(e) => setGlobalFilter(e.target.value)}
                            placeholder="Pretraga..."
                            className="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pl-11 pr-4 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800 xl:w-[300px]"
                        />
                    </div>
                    {/* Optional: Add a Download button here if needed */}
                </div>
            </div>

            {/* Table */}
            <div className="max-w-full overflow-x-auto custom-scrollbar">
                <table className="w-full table-auto border-collapse">
                    <thead>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <th
                                        key={header.id}
                                        className="px-4 py-3 border border-gray-100 dark:border-white/[0.05] text-left text-sm font-medium text-gray-700 dark:text-gray-400 bg-gray-50 dark:bg-gray-800"
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
                                        className="px-4 py-4 border border-gray-100 dark:border-white/[0.05] dark:text-white/90 whitespace-nowrap text-sm"
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
            <div className="flex items-center justify-between gap-2 px-6 py-4 sm:justify-normal">
                {/* Previous Button */}
                <button
                    onClick={() =>
                        handlePageChange(pagination.current_page - 1)
                    }
                    disabled={pagination.current_page === 1}
                    className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 sm:p-2.5 text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
                >
                    <span>
                        <ChevronLeftIcon className="h-4 w-4" />
                    </span>
                </button>

                {/* For small screens, display a text summary */}
                <span className="block text-sm font-medium text-gray-700 dark:text-gray-400 sm:hidden">
                    Page {pagination.current_page} of {pagination.last_page}
                </span>

                {/* For medium screens and up, show page numbers */}
                <ul className="hidden items-center gap-0.5 sm:flex">
                    {(() => {
                        const pages = [];
                        for (
                            let page = 1;
                            page <= pagination.last_page;
                            page++
                        ) {
                            pages.push(page);
                        }
                        return pages.map((page) => (
                            <li key={page}>
                                <button
                                    onClick={() => handlePageChange(page)}
                                    className={`flex items-center justify-center w-10 h-10 text-sm font-medium rounded-lg ${
                                        pagination.current_page === page
                                            ? "bg-brand-500 text-white"
                                            : "bg-white text-gray-700 hover:bg-brand-500 hover:text-white dark:text-gray-400 dark:hover:text-white"
                                    }`}
                                >
                                    {page}
                                </button>
                            </li>
                        ));
                    })()}
                </ul>

                {/* Next Button */}
                <button
                    onClick={() =>
                        handlePageChange(pagination.current_page + 1)
                    }
                    disabled={pagination.current_page === pagination.last_page}
                    className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 sm:p-2.5 text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
                >
                    <span>
                        <ChevronRightIcon className="h-4 w-4" />
                    </span>
                </button>
            </div>
        </div>
    );
};

export default MembersDataTable;
