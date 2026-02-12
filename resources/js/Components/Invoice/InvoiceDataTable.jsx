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
import { Modal } from "@/Components/ui/modal";
import toast from "react-hot-toast";
import Radio from "@/Components/form/input/Radio";
import { TrashIcon, CalendarDaysIcon } from "@heroicons/react/24/outline";
import { useModal } from "@/hooks/useModal";
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/themes/light.css";

const InvoicesDataTable = ({
    data,
    pagination,
    pageSizeOptions = [10, 20, 50],
    workshops = [],
    paymentStatuses = [],
    groups = [],
    initialWorkshopId = "",
    initialPaymentStatus = "",
    initialGroupId = "",
    initialFilter = "",
    initialMonth = "",
}) => {
    const [globalFilter, setGlobalFilter] = useState(initialFilter);
    const [workshopId, setWorkshopId] = useState(initialWorkshopId);
    const [paymentStatus, setPaymentStatus] = useState(initialPaymentStatus);
    const [groupId, setGroupId] = useState(initialGroupId);
    const [monthFilter, setMonthFilter] = useState(initialMonth);
    const [isWorkshopDropdownOpen, setIsWorkshopDropdownOpen] = useState(false);
    const [isStatusDropdownOpen, setIsStatusDropdownOpen] = useState(false);
    const [isGroupDropdownOpen, setIsGroupDropdownOpen] = useState(false);

    console.log(data);
    

    // Bulk selection state, list of selected invoice IDs
    const [selectedRows, setSelectedRows] = useState([]);
    
    // Modal state for confirmation
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [pendingAction, setPendingAction] = useState(null); // 'Plaćeno' or 'Otvoreno'

    // Per-invoice details modal state
    const [showInvoiceModal, setShowInvoiceModal] = useState(false);
    const [activeInvoice, setActiveInvoice] = useState(null);
    const [modalStatus, setModalStatus] = useState("");
    
    // Delete invoice state
    const deleteInvoiceModal = useModal();
    const [invoiceToDelete, setInvoiceToDelete] = useState(null);

    const openInvoiceDetails = (invoice) => {
        console.log(invoice);
        setActiveInvoice(invoice);
        setModalStatus(invoice?.payment_status || "");
        setShowInvoiceModal(true);
    };

    const closeInvoiceDetails = () => {
        setShowInvoiceModal(false);
        setActiveInvoice(null);
        setModalStatus("");
    };

    const saveInvoiceStatus = () => {
        if (!activeInvoice || !modalStatus) return;
        if (modalStatus === "Plaćeno") {
            router.patch(
                route("invoices.markPaid", activeInvoice.id),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success("Status računa promjenjen");
                        closeInvoiceDetails();
                    },
                    onError: () => toast.error("Greška pri ažuriranju statusa"),
                },
            );
        } else {
            router.patch(
                route("invoices.updateStatus", activeInvoice.id),
                { status: "Otvoreno" },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success("Status računa promjenjen");
                        closeInvoiceDetails();
                    },
                    onError: () => toast.error("Greška pri ažuriranju statusa"),
                },
            );
        }
    };

    const handleDeleteInvoice = () => {
        if (!activeInvoice) return;
        setInvoiceToDelete(activeInvoice);
        closeInvoiceDetails();
        deleteInvoiceModal.openModal();
    };

    const confirmDeleteInvoice = () => {
        if (!invoiceToDelete) return;

        router.delete(
            route("invoices.destroy", invoiceToDelete.id),
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success("Račun uspješno obrisan");
                    deleteInvoiceModal.closeModal();
                    setInvoiceToDelete(null);
                },
                onError: () => {
                    toast.error("Greška pri brisanju računa");
                    deleteInvoiceModal.closeModal();
                    setInvoiceToDelete(null);
                },
            }
        );
    };

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
                        invoice={row.original}
                        onOpenDetails={(invoice) => openInvoiceDetails(invoice)}
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
                    group_id: groupId,
                    month: monthFilter,
                },
                { preserveState: true },
            );
        }, 500);

        return () => clearTimeout(timeout);
    }, [globalFilter, workshopId, paymentStatus, groupId, monthFilter, pagination.current_page, pagination.per_page]);

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
            group_id: groupId,
            month: monthFilter,
        });
    };

    const handlePageSizeChange = (size) => {
        router.get(route("invoices.index"), {
            page: 1,
            per_page: size,
            filter: globalFilter,
            workshop_id: workshopId,
            payment_status: paymentStatus,
            group_id: groupId,
            month: monthFilter,
        });
    };

    const handleMonthChange = (selectedDates, dateStr) => {
        // dateStr will be in Y-m format when dateFormat is "Y-m"
        setMonthFilter(dateStr || "");
    };

    return (
        <div className="overflow-hidden rounded-xl bg-white dark:bg-white/[0.03]">
            {/* Bulk Actions Bar */}
            {selectedRows.length > 0 && (
                <div className="flex items-center justify-between gap-3 bg-brand-50 border-b border-brand-100 px-4 py-3 dark:bg-brand-900/20 dark:border-brand-800">
                    <span className="text-sm font-medium text-brand-700 dark:text-brand-300">
                        Označeno {selectedRows.length}{" "}
                        {selectedRows.length === 1 ? "račun" : "računa"}
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
                        {/* Month Filter */}
                        <div className="relative inline-block">
                            <div className="relative">
                                <Flatpickr
                                    value={monthFilter}
                                    onChange={handleMonthChange}
                                    options={{
                                        dateFormat: "Y-m",
                                        mode: "single",
                                        defaultDate: monthFilter || null,
                                    }}
                                    placeholder="Mjesec (YYYY-MM)"
                                    className="h-9 px-3 py-2 pr-10 text-sm border border-gray-200 rounded-lg bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 min-w-[160px]"
                                />
                                <span className="absolute text-gray-500 -translate-y-1/2 pointer-events-none right-3 top-1/2 dark:text-gray-400">
                                    <CalendarDaysIcon className="h-4 w-4" />
                                </span>
                            </div>
                        </div>

                        {/* Reset Filters Button */}
                        <button
                            onClick={() => {
                                setGlobalFilter("");
                                setWorkshopId("");
                                setPaymentStatus("");
                                setGroupId("");
                                setMonthFilter("");
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

                        {/* Group Filter Dropdown */}
                        <div className="relative inline-block">
                            <button
                                onClick={() => setIsStatusDropdownOpen(false) || setIsWorkshopDropdownOpen(false) || setIsGroupDropdownOpen(!isGroupDropdownOpen)}
                                className="inline-flex items-center justify-between gap-2 px-4 py-3 text-sm font-medium rounded-lg dropdown-toggle border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:hover:bg-gray-800 min-w-[180px]"
                            >
                                <span className="truncate">
                                    {groupId ? (groups.find((g) => g.id == groupId)?.name || "Grupa") : "Sve grupe"}
                                </span>
                                <svg
                                    className={`duration-200 ease-in-out stroke-current ${isGroupDropdownOpen ? "rotate-180" : ""}`}
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
                                isOpen={isGroupDropdownOpen}
                                onClose={() => setIsGroupDropdownOpen(false)}
                            >
                                <ul className="flex flex-col gap-1 max-h-[300px] overflow-y-auto">
                                    <li>
                                        <DropdownItem
                                            onClick={() => {
                                                setGroupId("");
                                                setIsGroupDropdownOpen(false);
                                            }}
                                            className={`flex rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 ${
                                                !groupId ? "bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white" : "text-gray-700 dark:text-gray-300"
                                            }`}
                                            baseClassName=""
                                        >
                                            Sve grupe
                                        </DropdownItem>
                                    </li>
                                    {groups.length > 0 && (
                                        <li>
                                            <span className="my-1.5 block h-px w-full bg-gray-200 dark:bg-[#353C49]"></span>
                                        </li>
                                    )}
                                    {groups.map((g) => (
                                        <li key={g.id}>
                                            <DropdownItem
                                                onClick={() => {
                                                    setGroupId(g.id.toString());
                                                    setIsGroupDropdownOpen(false);
                                                }}
                                                className={`flex rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 ${
                                                    groupId == g.id.toString() ? "bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white" : "text-gray-700 dark:text-gray-300"
                                                }`}
                                                baseClassName=""
                                            >
                                                {g.name}
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
                            <tr
                                key={row.id}
                                className="hover:bg-gray-100 cursor-pointer"
                                onClick={() => openInvoiceDetails(row.original)}
                            >
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
            <Modal isOpen={showConfirmModal} onClose={closeConfirmModal} className="max-w-md w-full mx-4 p-6">
                <div>
                    <div className="flex items-center gap-4">
                        <div
                            className={`flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center ${
                                pendingAction === "Plaćeno"
                                    ? "bg-green-100"
                                    : "bg-yellow-100"
                            }`}
                        >
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
                                    ? `Označiti ${selectedRows.length} ${selectedRows.length === 1 ? "račun" : "računa"} kao plaćeno?`
                                    : `Označiti ${selectedRows.length} ${selectedRows.length === 1 ? "račun" : "računa"} kao otvoreno?`}
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

            {/* Invoice Details Modal */}
            <Modal isOpen={showInvoiceModal} onClose={closeInvoiceDetails} className="max-w-5xl w-full mx-4 p-6">
                {activeInvoice && (
                    <div>
                        <div className="flex items-start justify-between mb-4">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    Detalji računa
                                </h3>
                                <p className="text-sm text-gray-500">
                                    #{activeInvoice.reference_code}
                                </p>
                            </div>
                            <button
                                onClick={closeInvoiceDetails}
                                className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                aria-label="Zatvori"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            {/* Left: Meta (2 columns, 3 items) */}
                            <div className="sm:col-span-2">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Član
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {activeInvoice.member?.first_name}{" "}
                                            {activeInvoice.member?.last_name}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Radionica
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {activeInvoice.workshop?.name ||
                                                "-"}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Grupa
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {(() => {
                                                const wId =
                                                    activeInvoice.workshop
                                                        ?.id ||
                                                    activeInvoice.workshop_id;
                                                const groups =
                                                    activeInvoice.member
                                                        ?.workshop_groups ||
                                                    activeInvoice.member
                                                        ?.workshopGroups;
                                                if (Array.isArray(groups)) {
                                                    const match = groups.find(
                                                        (g) =>
                                                            g.workshop_id ===
                                                            wId,
                                                    );
                                                    return (
                                                        match?.group?.name ||
                                                        match?.group_name ||
                                                        "-"
                                                    );
                                                }
                                                return "-";
                                            })()}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Iznos
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {Number(
                                                activeInvoice.amount_due,
                                            ).toFixed(2)}{" "}
                                            €
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Dospijeće
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {new Date(
                                                activeInvoice.due_date,
                                            ).toLocaleDateString("hr-HR")}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs uppercase text-gray-500">
                                            Referenca
                                        </div>
                                        <div className="text-sm text-gray-900 dark:text-gray-100">
                                            {activeInvoice.reference_code}
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-xs uppercase text-gray-500">
                                        Napomena
                                    </div>
                                    <div className="text-sm text-gray-900 dark:text-gray-100">
                                        {activeInvoice.notes || "—"}
                                    </div>
                                </div>
                            </div>

                            {/* Right: Actions with status toggle */}
                            <div className="flex flex-col gap-4">
                                <div>
                                    <div className="text-xs uppercase text-gray-500 mb-2">
                                        Status
                                    </div>
                                    <div className="flex items-center gap-6">
                                        <Radio
                                            id="status-paid"
                                            name="invoice-status"
                                            value="Plaćeno"
                                            checked={modalStatus === "Plaćeno"}
                                            label="Plaćeno"
                                            onChange={setModalStatus}
                                            className=""
                                        />
                                        <Radio
                                            id="status-open"
                                            name="invoice-status"
                                            value="Otvoreno"
                                            checked={modalStatus === "Otvoreno"}
                                            label="Otvoreno"
                                            onChange={setModalStatus}
                                        />
                                    </div>
                                </div>

                                <a
                                    href={route(
                                        "invoices.slip",
                                        activeInvoice.id,
                                    )}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium border rounded-lg hover:bg-gray-50 dark:border-gray-700"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    Uplatnica (PDF)
                                </a>
                                <button
                                    disabled
                                    className="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium border rounded-lg text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed"
                                    title="Uskoro dostupno"
                                >
                                    Pošalji e-mail
                                </button>
                                <button
                                    onClick={handleDeleteInvoice}
                                    className="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium border rounded-lg text-red-600 border-red-200 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 transition"
                                >
                                    <TrashIcon className="h-4 w-4" />
                                    Brisanje računa
                                </button>

                                <div className="flex items-center justify-end gap-2 pt-2">
                                    <button
                                        onClick={closeInvoiceDetails}
                                        className="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                    >
                                        Zatvori
                                    </button>
                                    <button
                                        onClick={saveInvoiceStatus}
                                        className="px-3 py-2 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-lg"
                                    >
                                        Spremi status
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </Modal>

            {/* Delete Invoice Confirmation Modal */}
            <Modal
                isOpen={deleteInvoiceModal.isOpen}
                onClose={() => {
                    deleteInvoiceModal.closeModal();
                    setInvoiceToDelete(null);
                }}
                className="max-w-[500px] p-5 lg:p-10"
            >
                <div className="text-center">
                    <div className="relative flex items-center justify-center z-1 mb-7">
                        <svg
                            className="fill-error-50 dark:fill-error-500/15"
                            width="90"
                            height="90"
                            viewBox="0 0 90 90"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M34.364 6.85053C38.6205 -2.28351 51.3795 -2.28351 55.636 6.85053C58.0129 11.951 63.5594 14.6722 68.9556 13.3853C78.6192 11.0807 86.5743 21.2433 82.2185 30.3287C79.7862 35.402 81.1561 41.5165 85.5082 45.0122C93.3019 51.2725 90.4628 63.9451 80.7747 66.1403C75.3648 67.3661 71.5265 72.2695 71.5572 77.9156C71.6123 88.0265 60.1169 93.6664 52.3918 87.3184C48.0781 83.7737 41.9219 83.7737 37.6082 87.3184C29.8831 93.6664 18.3877 88.0266 18.4428 77.9156C18.4735 72.2695 14.6352 67.3661 9.22531 66.1403C-0.462787 63.9451 -3.30193 51.2725 4.49185 45.0122C8.84391 41.5165 10.2138 35.402 7.78151 30.3287C3.42572 21.2433 11.3808 11.0807 21.0444 13.3853C26.4406 14.6722 31.9871 11.951 34.364 6.85053Z"
                                fill=""
                                fillOpacity=""
                            />
                        </svg>

                        <span className="absolute -translate-x-1/2 -translate-y-1/2 left-1/2 top-1/2">
                            <svg
                                className="fill-error-600 dark:fill-error-500"
                                width="38"
                                height="38"
                                viewBox="0 0 38 38"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M9.62684 11.7496C9.04105 11.1638 9.04105 10.2141 9.62684 9.6283C10.2126 9.04252 11.1624 9.04252 11.7482 9.6283L18.9985 16.8786L26.2485 9.62851C26.8343 9.04273 27.7841 9.04273 28.3699 9.62851C28.9556 10.2143 28.9556 11.164 28.3699 11.7498L21.1198 18.9999L28.3699 26.25C28.9556 26.8358 28.9556 27.7855 28.3699 28.3713C27.7841 28.9571 26.8343 28.9571 26.2485 28.3713L18.9985 21.1212L11.7482 28.3715C11.1624 28.9573 10.2126 28.9573 9.62684 28.3715C9.04105 27.7857 9.04105 26.836 9.62684 26.2502L16.8771 18.9999L9.62684 11.7496Z"
                                    fill=""
                                />
                            </svg>
                        </span>
                    </div>

                    <h4 className="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90 sm:text-title-sm">
                        Brisanje računa
                    </h4>
                    <p className="text-sm leading-6 text-gray-500 dark:text-gray-400 mb-4">
                        Jeste li sigurni da želite obrisati račun{" "}
                        <strong>{invoiceToDelete?.reference_code}</strong>? Ova akcija se ne može poništiti.
                    </p>
                    {invoiceToDelete && (
                        <div className="mb-4 text-sm text-gray-700 dark:text-gray-300">
                            <p><strong>Iznos:</strong> {Number(invoiceToDelete.amount_due || 0).toFixed(2)} EUR</p>
                            <p><strong>Status:</strong> {invoiceToDelete.payment_status}</p>
                        </div>
                    )}
                    <div className="flex items-center justify-center w-full gap-3 mt-7">
                        <button
                            onClick={() => {
                                deleteInvoiceModal.closeModal();
                                setInvoiceToDelete(null);
                            }}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            Odustani
                        </button>
                        <button
                            onClick={confirmDeleteInvoice}
                            className="px-4 py-2 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-lg"
                        >
                            Da, obriši
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default InvoicesDataTable;
