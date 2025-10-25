import React, { useState, useEffect, useMemo } from "react";
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
} from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import InvoiceActionsDropdown from "./InvoiceActionColumn";
import { Dropdown } from "@/ui/dropdown/Dropdown";
import { DropdownItem } from "@/ui/dropdown/DropdownItem";
import Modal from "@/Components/Modal";
import toast from "react-hot-toast";

const InvoicesDataTable = ({
    data,
    pagination,
    pageSizeOptions = [10, 20, 50],
    workshops = [],
    paymentStatuses = [],
    initialWorkshopId = "",
    initialPaymentStatus = "",
    initialFilter = "",
}) => {
    const [globalFilter, setGlobalFilter] = useState(initialFilter);
    const [workshopId, setWorkshopId] = useState(initialWorkshopId);
    const [paymentStatus, setPaymentStatus] = useState(initialPaymentStatus);
    const [isWorkshopDropdownOpen, setIsWorkshopDropdownOpen] = useState(false);
    const [isStatusDropdownOpen, setIsStatusDropdownOpen] = useState(false);
    

    // Bulk selection state, list of selected invoice IDs
    const [selectedRows, setSelectedRows] = useState([]);
    
    // Modal state for confirmation
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [pendingAction, setPendingAction] = useState(null); // 'Plaćeno' or 'Otvoreno'

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

    // Select-all checkbox handler
    const handleSelectAll = (e) => {
        if (e.target.checked) {
            setSelectedRows(data.map((row) => row.id));
        } else {
            setSelectedRows([]);
        }
    };

    // Individual row selection handler
    const handleSelectRow = (e, id) => {
        if (e.target.checked) {
            setSelectedRows((prev) => [...prev, id]);
        } else {
            setSelectedRows((prev) => prev.filter((selectedId) => selectedId !== id));
        }
    };

    // Open confirmation modal
    const openConfirmModal = (targetStatus) => {
        if (selectedRows.length === 0) return;
        setPendingAction(targetStatus);
        setShowConfirmModal(true);
    };

    // Close confirmation modal
    const closeConfirmModal = () => {
        setShowConfirmModal(false);
        setPendingAction(null);
    };

    // Handler for toggling bulk invoice status (Paid/Open)
    const handleToggleBulkInvoiceStatus = () => {
        if (!pendingAction) return;

        router.post(route("invoices.toggleBulkInvoiceStatus"), {
            invoice_ids: selectedRows,
            status: pendingAction,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedRows([]);
                closeConfirmModal();
                toast.success("Status računa promjenjen");
            },
            onError: () => {
                toast.error("Greška pri ažuriranju statusa");
            },
        });
    };

    // Compose new columns array with checkbox as the first column
    const columnsWithCheckbox = useMemo(
        () => [
            {
                id: "select",
                header: () => (
                    <input
                        type="checkbox"
                        checked={selectedRows.length === data.length && data.length > 0}
                        ref={el => {
                            if (el) {
                                el.indeterminate = selectedRows.length > 0 && selectedRows.length < data.length;
                            }
                        }}
                        onChange={handleSelectAll}
                        aria-label="Select all rows"
                        className="accent-brand-500 size-4"
                    />
                ),
                cell: ({ row }) => (
                    <input
                        type="checkbox"
                        checked={selectedRows.includes(row.original.id)}
                        onChange={e => handleSelectRow(e, row.original.id)}
                        aria-label={`Odaberi redak #${row.original.id}`}
                        className="accent-brand-500 size-4"
                    />
                ),
                size: 32,
                enableSorting: false,
                enableColumnFilter: false,
            },
            ...columns,
        ],
        [data, selectedRows, columns]
    );

    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get(
                route("invoices.index"),
                {
                    page: pagination.current_page,
                    per_page: pagination.per_page,
                    filter: globalFilter,
                    workshop_id: workshopId,
                    payment_status: paymentStatus,
                },
                { preserveState: true },
            );
        }, 500);

        return () => clearTimeout(timeout);
    }, [globalFilter, workshopId, paymentStatus, pagination.current_page, pagination.per_page]);

    const table = useReactTable({
        data,
        columns: columnsWithCheckbox,
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
            workshop_id: workshopId,
            payment_status: paymentStatus,
        });
    };

    const handlePageSizeChange = (size) => {
        router.get(route("invoices.index"), {
            page: 1,
            per_page: size,
            filter: globalFilter,
            workshop_id: workshopId,
            payment_status: paymentStatus,
        });
    };

    return (
        <div className="overflow-hidden rounded-xl bg-white dark:bg-white/[0.03]">
            {/* Bulk Actions Bar */}
            {selectedRows.length > 0 && (
                <div className="flex items-center justify-between gap-3 bg-brand-50 border-b border-brand-100 px-4 py-3 dark:bg-brand-900/20 dark:border-brand-800">
                    <span className="text-sm font-medium text-brand-700 dark:text-brand-300">
                        Označeno {selectedRows.length} {selectedRows.length === 1 ? 'račun' : 'računa'}
                    </span>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => openConfirmModal("Plaćeno")}
                            className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition"
                        >
                            <svg
                                className="w-4 h-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                            Plaćeno
                        </button>
                        <button
                            onClick={() => openConfirmModal("Otvoreno")}
                            className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 rounded-lg transition"
                        >
                            <svg
                                className="w-4 h-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            Otvoreno
                        </button>
                        <button
                            onClick={() => setSelectedRows([])}
                            className="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            Poništi
                        </button>
                    </div>
                </div>
            )}
            
            {/* Table header */}
            <div className="flex flex-col gap-4 px-4 py-4 border border-b-0 border-gray-100 dark:border-white/[0.05] rounded-t-xl">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
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
                    <div className="flex flex-col sm:flex-row gap-2 sm:gap-3">
                        {/* Reset Filters Button */}
                        <button
                            onClick={() => {
                                setGlobalFilter("");
                                setWorkshopId("");
                                setPaymentStatus("");
                            }}
                            className="inline-flex items-center gap-1 px-3 py-2 text-sm rounded-lg border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:hover:bg-gray-800 text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-gray-100 transition"
                            title="Poništi filtere"
                            type="button"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 20 20"
                                className="w-4 h-4"
                            >
                                <path
                                    stroke="currentColor"
                                    strokeWidth="1.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M4.5 15.5 15.5 4.5M4.5 4.5l11 11"
                                />
                            </svg>
                            Reset
                        </button>
                        {/* Workshop Filter Dropdown */}
                        <div className="relative inline-block">
                            <button
                                onClick={() =>
                                    setIsWorkshopDropdownOpen(
                                        !isWorkshopDropdownOpen,
                                    )
                                }
                                className="inline-flex items-center justify-between gap-2 px-4 py-3 text-sm font-medium rounded-lg dropdown-toggle border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:hover:bg-gray-800 min-w-[180px]"
                            >
                                <span className="truncate">
                                    {workshopId
                                        ? workshops.find(
                                              (w) => w.id == workshopId,
                                          )?.name
                                        : "Sve radionice"}
                                </span>
                                <svg
                                    className={`duration-200 ease-in-out stroke-current ${
                                        isWorkshopDropdownOpen
                                            ? "rotate-180"
                                            : ""
                                    }`}
                                    width="20"
                                    height="20"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M4.79199 7.396L10.0003 12.6043L15.2087 7.396"
                                        stroke=""
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </button>

                            <Dropdown
                                className="absolute left-0 top-full z-40 mt-2 w-full min-w-[260px] rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-[#1E2635]"
                                isOpen={isWorkshopDropdownOpen}
                                onClose={() => setIsWorkshopDropdownOpen(false)}
                            >
                                <ul className="flex flex-col gap-1 max-h-[300px] overflow-y-auto">
                                    <li>
                                        <DropdownItem
                                            onClick={() => {
                                                setWorkshopId("");
                                                setIsWorkshopDropdownOpen(
                                                    false,
                                                );
                                            }}
                                            className={`flex rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 ${
                                                !workshopId
                                                    ? "bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white"
                                                    : "text-gray-700 dark:text-gray-300"
                                            }`}
                                            baseClassName=""
                                        >
                                            Sve radionice
                                        </DropdownItem>
                                    </li>
                                    {workshops.length > 0 && (
                                        <li>
                                            <span className="my-1.5 block h-px w-full bg-gray-200 dark:bg-[#353C49]"></span>
                                        </li>
                                    )}
                                    {workshops.map((workshop) => (
                                        <li key={workshop.id}>
                                            <DropdownItem
                                                onClick={() => {
                                                    setWorkshopId(
                                                        workshop.id.toString(),
                                                    );
                                                    setIsWorkshopDropdownOpen(
                                                        false,
                                                    );
                                                }}
                                                className={`flex rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 ${
                                                    workshopId ==
                                                    workshop.id.toString()
                                                        ? "bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white"
                                                        : "text-gray-700 dark:text-gray-300"
                                                }`}
                                                baseClassName=""
                                            >
                                                {workshop.name}
                                            </DropdownItem>
                                        </li>
                                    ))}
                                </ul>
                            </Dropdown>
                        </div>

                        {/* Payment Status Filter Dropdown */}
                        <div className="relative inline-block">
                            <button
                                onClick={() =>
                                    setIsStatusDropdownOpen(
                                        !isStatusDropdownOpen,
                                    )
                                }
                                className="inline-flex items-center justify-between gap-2 px-4 py-3 text-sm font-medium rounded-lg dropdown-toggle border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:hover:bg-gray-800 min-w-[180px]"
                            >
                                <span className="truncate">
                                    {paymentStatus || "Svi statusi"}
                                </span>
                                <svg
                                    className={`duration-200 ease-in-out stroke-current ${
                                        isStatusDropdownOpen ? "rotate-180" : ""
                                    }`}
                                    width="20"
                                    height="20"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M4.79199 7.396L10.0003 12.6043L15.2087 7.396"
                                        stroke=""
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </button>

                            <Dropdown
                                className="absolute left-0 top-full z-40 mt-2 w-full min-w-[260px] rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-[#1E2635]"
                                isOpen={isStatusDropdownOpen}
                                onClose={() => setIsStatusDropdownOpen(false)}
                            >
                                <ul className="flex flex-col gap-1">
                                    <li>
                                        <DropdownItem
                                            onClick={() => {
                                                setPaymentStatus("");
                                                setIsStatusDropdownOpen(false);
                                            }}
                                            className={`flex rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 ${
                                                !paymentStatus
                                                    ? "bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white"
                                                    : "text-gray-700 dark:text-gray-300"
                                            }`}
                                            baseClassName=""
                                        >
                                            Svi statusi
                                        </DropdownItem>
                                    </li>
                                    {paymentStatuses.length > 0 && (
                                        <li>
                                            <span className="my-1.5 block h-px w-full bg-gray-200 dark:bg-[#353C49]"></span>
                                        </li>
                                    )}
                                    {paymentStatuses.map((status) => (
                                        <li key={status}>
                                            <DropdownItem
                                                onClick={() => {
                                                    setPaymentStatus(status);
                                                    setIsStatusDropdownOpen(
                                                        false,
                                                    );
                                                }}
                                                className={`flex rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 ${
                                                    paymentStatus === status
                                                        ? "bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white"
                                                        : "text-gray-700 dark:text-gray-300"
                                                }`}
                                                baseClassName=""
                                            >
                                                {status}
                                            </DropdownItem>
                                        </li>
                                    ))}
                                </ul>
                            </Dropdown>
                        </div>

                        <input
                            type="text"
                            value={globalFilter}
                            onChange={(e) => setGlobalFilter(e.target.value)}
                            placeholder="Pretraga..."
                            className="h-11 px-4 py-2 rounded-lg border w-full sm:w-[300px]"
                        />
                    </div>
                </div>
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

            {/* Confirmation Modal */}
            <Modal show={showConfirmModal} onClose={closeConfirmModal} maxWidth="md">
                <div className="p-6">
                    <div className="flex items-center gap-4">
                        <div className={`flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center ${
                            pendingAction === "Plaćeno" 
                                ? "bg-green-100" 
                                : "bg-yellow-100"
                        }`}>
                            {pendingAction === "Plaćeno" ? (
                                <svg
                                    className="w-6 h-6 text-green-600"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            ) : (
                                <svg
                                    className="w-6 h-6 text-yellow-600"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            )}
                        </div>
                        <div className="flex-1">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                {pendingAction === "Plaćeno" 
                                    ? "Označi kao plaćeno" 
                                    : "Označi kao otvoreno"}
                            </h3>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {pendingAction === "Plaćeno"
                                    ? `Označiti ${selectedRows.length} ${selectedRows.length === 1 ? 'račun' : 'računa'} kao plaćeno?`
                                    : `Označiti ${selectedRows.length} ${selectedRows.length === 1 ? 'račun' : 'računa'} kao otvoreno?`}
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <button
                            onClick={closeConfirmModal}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            Odustani
                        </button>
                        <button
                            onClick={handleToggleBulkInvoiceStatus}
                            className={`px-4 py-2 text-sm font-medium text-white rounded-lg transition ${
                                pendingAction === "Plaćeno"
                                    ? "bg-green-600 hover:bg-green-700"
                                    : "bg-yellow-600 hover:bg-yellow-700"
                            }`}
                        >
                            Potvrdi
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default InvoicesDataTable;
