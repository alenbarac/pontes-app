import React, { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import {
    Table,
    TableHeader,
    TableRow,
    TableCell,
    TableBody,
} from "@/Components/ui/table";
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    DocumentTextIcon,
    EyeIcon,
} from "@heroicons/react/24/outline";
import Button from "@/Components/ui/button/Button";
import { useModal } from "@/hooks/useModal";
import BulkReassignModal from "@/Components/MemberGroup/BulkReassignModal";
import TemplateSelectorModal from "@/Components/Documents/TemplateSelectorModal";
import toast from "react-hot-toast";

export default function Show({
    group,
    members,
    membersMeta,
    statistics,
    otherGroups,
    search: initialSearch = "",
}) {
    const [selectedMembers, setSelectedMembers] = useState([]);
    const [search, setSearch] = useState(initialSearch);
    const bulkReassignModal = useModal();
    const documentModal = useModal();
    const [documentType, setDocumentType] = useState("ispricnica");

    const workshopId = group.workshop?.id;

    const handleSelectAll = () => {
        if (selectedMembers.length === members.data.length) {
            setSelectedMembers([]);
        } else {
            setSelectedMembers(members.data.map((m) => m.id));
        }
    };

    const handleSelectMember = (memberId) => {
        setSelectedMembers((prev) =>
            prev.includes(memberId)
                ? prev.filter((id) => id !== memberId)
                : [...prev, memberId]
        );
    };

    const handleSearch = (e) => {
        const value = e.target.value;
        setSearch(value);
        router.get(
            route("member-groups.show", group.id),
            { search: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const handleBulkReassign = () => {
        if (selectedMembers.length === 0) {
            toast.error("Molimo odaberite barem jednog člana.");
            return;
        }
        bulkReassignModal.openModal();
    };

    const handleGenerateExcuse = (member) => {
        setSelectedMembers([member.id]);
        setDocumentType(null); // Let user choose in modal
        documentModal.openModal();
    };

    const handleBulkGenerateDocuments = () => {
        if (selectedMembers.length === 0) {
            toast.error("Molimo odaberite barem jednog člana.");
            return;
        }
        setDocumentType(null); // Let user choose in modal
        documentModal.openModal();
    };

    const handleDocumentSuccess = () => {
        setSelectedMembers([]);
        documentModal.closeModal();
    };

    const handlePageChange = (page) => {
        if (page !== membersMeta.current_page) {
            router.get(
                route("member-groups.show", group.id),
                { page, search },
                { preserveScroll: true }
            );
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Grupa: ${group.name}`} />
            <Breadcrumb pageName={`Grupa: ${group.name}`} />

            <div className="space-y-6">
                {/* Group Info Card */}
                <ComponentCard title="Informacije o grupi">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                Naziv grupe
                            </p>
                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                {group.name}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                Radionica
                            </p>
                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                {group.workshop?.name ?? "—"}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                Broj članova
                            </p>
                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                {statistics.total_members}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                Aktivni članovi
                            </p>
                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                {statistics.active_members}
                            </p>
                        </div>
                    </div>
                    {group.description && (
                        <div className="mt-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                Opis
                            </p>
                            <p className="text-sm text-gray-800 dark:text-white/90">
                                {group.description}
                            </p>
                        </div>
                    )}
                </ComponentCard>

                {/* Members Table */}
                <ComponentCard
                    title="Članovi grupe"
                    headerAction={
                        selectedMembers.length > 0 && (
                            <div className="flex gap-2">
                                <Button
                                    onClick={handleBulkReassign}
                                    variant="primary"
                                    size="sm"
                                >
                                    Premjesti odabrane ({selectedMembers.length})
                                </Button>
                                <Button
                                    onClick={handleBulkGenerateDocuments}
                                    variant="outline"
                                    size="sm"
                                    startIcon={
                                        <DocumentTextIcon className="h-4 w-4" />
                                    }
                                >
                                    Generiraj dokument
                                </Button>
                            </div>
                        )
                    }
                >
                    {/* Search */}
                    <div className="mb-4">
                        <input
                            type="text"
                            placeholder="Pretraži članove..."
                            value={search}
                            onChange={handleSearch}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>

                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
                        <div className="max-w-full overflow-x-auto">
                            <Table>
                                <TableHeader className="border-b border-gray-200 dark:border-white/[0.05] bg-gray-50">
                                    <TableRow>
                                        <TableCell
                                            isHeader
                                            className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={
                                                    selectedMembers.length ===
                                                    members.data.length &&
                                                    members.data.length > 0
                                                }
                                                onChange={handleSelectAll}
                                                className="rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                            />
                                        </TableCell>
                                        <TableCell
                                            isHeader
                                            className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                        >
                                            Ime i prezime
                                        </TableCell>
                                        <TableCell
                                            isHeader
                                            className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                        >
                                            Email
                                        </TableCell>
                                        <TableCell
                                            isHeader
                                            className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                        >
                                            Telefon
                                        </TableCell>
                                        <TableCell
                                            isHeader
                                            className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                        >
                                            Status
                                        </TableCell>
                                        <TableCell
                                            isHeader
                                            className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                                        >
                                            Akcije
                                        </TableCell>
                                    </TableRow>
                                </TableHeader>

                                <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                                    {members.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="px-5 py-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                Nema članova u ovoj grupi.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        members.data.map((member) => (
                                            <TableRow key={member.id}>
                                                <TableCell className="px-5 py-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedMembers.includes(
                                                            member.id
                                                        )}
                                                        onChange={() =>
                                                            handleSelectMember(
                                                                member.id
                                                            )
                                                        }
                                                        className="rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                                    />
                                                </TableCell>
                                                <TableCell className="px-5 py-4 text-start text-theme-sm dark:text-white/90">
                                                    <Link
                                                        href={route(
                                                            "members.show",
                                                            member.id
                                                        )}
                                                        className="hover:text-brand-500"
                                                    >
                                                        {member.first_name}{" "}
                                                        {member.last_name}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                    {member.email || "—"}
                                                </TableCell>
                                                <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                                    {member.phone_number || "—"}
                                                </TableCell>
                                                <TableCell className="px-5 py-4 text-start">
                                                    <span
                                                        className={`px-2 py-1 text-xs rounded ${
                                                            member.is_active
                                                                ? "bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400"
                                                                : "bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400"
                                                        }`}
                                                    >
                                                        {member.is_active
                                                            ? "Aktivan"
                                                            : "Neaktivan"}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="px-5 py-4 text-start">
                                                    <div className="flex gap-2">
                                                        <Link
                                                            href={route(
                                                                "members.show",
                                                                member.id
                                                            )}
                                                            className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400"
                                                            title="Pregled profila"
                                                        >
                                                            <EyeIcon className="h-5 w-5" />
                                                        </Link>
                                                        <button
                                                            onClick={() =>
                                                                handleGenerateExcuse(
                                                                    member
                                                                )
                                                            }
                                                            className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400"
                                                            title="Generiraj izvještaj/opravdanje"
                                                        >
                                                            <DocumentTextIcon className="h-5 w-5" />
                                                        </button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination */}
                        {membersMeta.last_page > 1 && (
                            <div className="flex items-center justify-between gap-2 px-6 py-4 sm:justify-normal">
                                <button
                                    onClick={() =>
                                        handlePageChange(
                                            membersMeta.current_page - 1
                                        )
                                    }
                                    disabled={membersMeta.current_page === 1}
                                    className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 sm:p-2.5 text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50"
                                >
                                    <ChevronLeftIcon className="h-4 w-4" />
                                </button>

                                <span className="block text-sm font-medium text-gray-700 dark:text-gray-400 sm:hidden">
                                    Stranica {membersMeta.current_page} od{" "}
                                    {membersMeta.last_page}
                                </span>

                                <ul className="hidden items-center gap-0.5 sm:flex">
                                    {Array.from(
                                        { length: membersMeta.last_page },
                                        (_, i) => i + 1
                                    ).map((page) => (
                                        <li key={page}>
                                            <button
                                                onClick={() =>
                                                    handlePageChange(page)
                                                }
                                                className={`flex items-center justify-center w-10 h-10 text-sm font-medium rounded-lg ${
                                                    membersMeta.current_page ===
                                                    page
                                                        ? "bg-brand-500 text-white"
                                                        : "bg-white text-gray-700 hover:bg-brand-500 hover:text-white dark:text-gray-400 dark:hover:text-white"
                                                }`}
                                            >
                                                {page}
                                            </button>
                                        </li>
                                    ))}
                                </ul>

                                <button
                                    onClick={() =>
                                        handlePageChange(
                                            membersMeta.current_page + 1
                                        )
                                    }
                                    disabled={
                                        membersMeta.current_page ===
                                        membersMeta.last_page
                                    }
                                    className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 sm:p-2.5 text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50"
                                >
                                    <ChevronRightIcon className="h-4 w-4" />
                                </button>
                            </div>
                        )}
                    </div>
                </ComponentCard>
            </div>

            {/* Bulk Reassign Modal */}
            <BulkReassignModal
                isOpen={bulkReassignModal.isOpen}
                onClose={bulkReassignModal.closeModal}
                selectedMembers={selectedMembers}
                members={members.data}
                currentGroup={group}
                otherGroups={otherGroups}
                workshopId={workshopId}
                onSuccess={() => {
                    setSelectedMembers([]);
                    bulkReassignModal.closeModal();
                }}
            />

            {/* Document Generation Modal */}
            <TemplateSelectorModal
                isOpen={documentModal.isOpen}
                onClose={documentModal.closeModal}
                selectedMembers={selectedMembers}
                members={members.data}
                templateType={documentType}
                onSuccess={handleDocumentSuccess}
            />
        </AuthenticatedLayout>
    );
}
