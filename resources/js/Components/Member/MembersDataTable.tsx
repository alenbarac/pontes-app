import React, { useState, useEffect, useMemo } from "react";
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
} from "@tanstack/react-table";
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    IdentificationIcon,
    TrashIcon,
} from "@heroicons/react/24/outline";
import { Link, router } from "@inertiajs/react";
import { useModal } from "@/hooks/useModal";
import { Modal } from "@/Components/ui/modal";
import Button from "@/Components/ui/button/Button";
import { Dropdown } from "@/ui/dropdown/Dropdown";
import { DropdownItem } from "@/ui/dropdown/DropdownItem";
import toast from "react-hot-toast";


const MembersDataTable = ({
    data,
    columns,
    pagination,
    pageSizeOptions = [5, 10, 20, 50],
    workshops = [],
    groups = [],
    initialWorkshopId = "",
    initialGroupId = "",
    initialFilter = "",
}) => {
    const [globalFilter, setGlobalFilter] = useState(initialFilter);
    const [workshopId, setWorkshopId] = useState(initialWorkshopId);
    const [groupId, setGroupId] = useState(initialGroupId);
    const [isWorkshopDropdownOpen, setIsWorkshopDropdownOpen] = useState(false);
    const [isGroupDropdownOpen, setIsGroupDropdownOpen] = useState(false);
    const deleteModal = useModal();
    const [memberToDelete, setMemberToDelete] = useState(null);

    // 1) When you confirm deletion, call your existing resource destroy:
    function confirmDelete() {
        router.delete(route("members.destroy", { member: memberToDelete.id }), {
            onSuccess: () => {
                setMemberToDelete(null);
                deleteModal.closeModal();
                toast.success('Član uspješno obrisan!');
            },
            onError: () => {
                setMemberToDelete(null);
                deleteModal.closeModal();
            },
        });
    }

    // 2) Override only the "actions" column cell
    const displayColumns = useMemo(() => {
        return columns.map((col) => {
            if (col.accessorKey === "actions") {
                return {
                    ...col,
                    cell: ({ row }) => {
                        const m = row.original;
                        return (
                            <div className="flex items-center space-x-2">
                                {/* Detail link */}
                                <Link
                                    href={route("members.show", m.id)}
                                    className="hover:text-blue-700"
                                >
                                    <IdentificationIcon className="h-5 w-5" />
                                </Link>
                                {/* Delete button */}
                                <button
                                    className="hover:text-red-700"
                                    onClick={() => {
                                        setMemberToDelete(m);
                                        deleteModal.openModal();
                                    }}
                                >
                                    <TrashIcon className="h-5 w-5" />
                                </button>
                            </div>
                        );
                    },
                };
            }
            return col;
        });
    }, [columns, deleteModal]);


    // 3) Inertia search & pagination handlers
    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get(
                route("members.index"),
                {
                    page: pagination.current_page,
                    per_page: pagination.per_page,
                    filter: globalFilter,
                    workshop_id: workshopId,
                    group_id: groupId,
                },
                { preserveState: true },
            );
        }, 500);
        return () => clearTimeout(timeout);
    }, [globalFilter, workshopId, groupId, pagination.current_page, pagination.per_page]);

    const table = useReactTable({
        data,
        columns: displayColumns,
        state: { globalFilter },
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: pagination.last_page,
    });

    const handlePageChange = (page) =>
        router.get(route("members.index"), {
            page,
            per_page: pagination.per_page,
            filter: globalFilter,
            workshop_id: workshopId,
            group_id: groupId,
        });

    const handlePageSizeChange = (size) =>
        router.get(route("members.index"), {
            page: 1,
            per_page: size,
            filter: globalFilter,
            workshop_id: workshopId,
            group_id: groupId,
        });

    return (
        <>
            <div className="overflow-hidden rounded-xl bg-white dark:bg-white/[0.03]">
                {/* --- Header (page size & search) --- */}
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

                    {/* Right side: Filters & Global Search */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        {/* Reset Filters Button */}
                        <button
                            onClick={() => {
                                setGlobalFilter("");
                                setWorkshopId("");
                                setGroupId("");
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
                                                    // Reset group when workshop changes
                                                    setGroupId("");
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

                        {/* Group Filter Dropdown */}
                        <div className="relative inline-block">
                            <button
                                onClick={() => {
                                    setIsWorkshopDropdownOpen(false);
                                    setIsGroupDropdownOpen(!isGroupDropdownOpen);
                                }}
                                className="inline-flex items-center justify-between gap-2 px-4 py-3 text-sm font-medium rounded-lg dropdown-toggle border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:hover:bg-gray-800 min-w-[180px]"
                            >
                                <span className="truncate">
                                    {groupId
                                        ? groups.find((g) => g.id == groupId)
                                              ?.name || "Grupa"
                                        : "Sve grupe"}
                                </span>
                                <svg
                                    className={`duration-200 ease-in-out stroke-current ${
                                        isGroupDropdownOpen ? "rotate-180" : ""
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
                                                !groupId
                                                    ? "bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white"
                                                    : "text-gray-700 dark:text-gray-300"
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
                                                    setIsGroupDropdownOpen(
                                                        false,
                                                    );
                                                }}
                                                className={`flex rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 ${
                                                    groupId == g.id.toString()
                                                        ? "bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white"
                                                        : "text-gray-700 dark:text-gray-300"
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
                                onChange={(e) =>
                                    setGlobalFilter(e.target.value)
                                }
                                placeholder="Pretraga..."
                                className="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pl-11 pr-4 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800 xl:w-[300px]"
                            />
                        </div>
                    </div>
                </div>

                {/* --- Table --- */}
                <div className="max-w-full overflow-x-auto">
                    <table className="w-full table-auto border-collapse">
                        <thead>
                            {table.getHeaderGroups().map((hg) => (
                                <tr key={hg.id}>
                                    {hg.headers.map((header) => (
                                        <th
                                            key={header.id}
                                            className="px-4 py-3 border text-left text-sm font-medium text-gray-700 dark:text-gray-400 bg-gray-50 dark:bg-gray-800"
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
                                            className="px-4 py-4 border text-sm dark:text-white/90 whitespace-nowrap"
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

                {/* --- Pagination --- */}
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
                        disabled={
                            pagination.current_page === pagination.last_page
                        }
                        className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 sm:p-2.5 text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
                    >
                        <span>
                            <ChevronRightIcon className="h-4 w-4" />
                        </span>
                    </button>
                </div>
            </div>

            {/* --- Confirm Delete Modal --- */}
            <Modal
                isOpen={deleteModal.isOpen}
                onClose={() => {
                    deleteModal.closeModal();
                    setMemberToDelete(null);
                }}
                className="max-w-md p-6"
            >
                <h4 className="text-xl font-semibold mb-4">Brisanje člana</h4>
                <p className="mb-6">
                    Jeste li sigurni da želite obrisati ovog člana?
                </p>
                <div className="flex justify-end gap-2">
                    <Button variant="primary" onClick={confirmDelete}>
                        Da, obriši
                    </Button>

                    <Button
                        variant="outline"
                        onClick={() => {
                            deleteModal.closeModal();
                            setMemberToDelete(null);
                        }}
                    >
                        Odustani
                    </Button>
                </div>
            </Modal>
        </>
    );
};

export default MembersDataTable;
