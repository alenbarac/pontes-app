import React, { useState } from "react";
import {
    Table,
    TableHeader,
    TableRow,
    TableCell,
    TableBody,
} from "@/Components/ui/table"; // Adjust if your Table components come from another library
import { ChevronLeftIcon, ChevronRightIcon, Cog8ToothIcon, EyeIcon, TrashIcon } from "@heroicons/react/24/outline";
import { useModal } from "@/hooks/useModal";
import { Link, router } from "@inertiajs/react";
import { Modal } from "../ui/modal";
import Button from "@/ui/button/Button";
import MemberGroupEditForm from "./MemberGroupEditForm";
import toast from "react-hot-toast";

export default function MemberGroupTable({ groups, workshops }) {
  const { data } = groups;

    const { isOpen, openModal, closeModal } = useModal();
      const confirmModal = useModal();
      const [editingGroup, setEditingGroup] = useState(null);
      const [groupToTerminate, setGroupToTerminate] = useState(null);

      const pagination = groups?.meta ?? {};

       const handlePageChange = (page) => {
           if (page !== pagination.current_page) {
               router.get(
                   route("member-groups.index"),
                   { page },
                   { preserveScroll: true },
               );
           }
       };

      const handleEdit = (g) => {
          setEditingGroup(g);
          openModal();
      };

      const handleTerminate = (g) => {
          setGroupToTerminate(g);
          confirmModal.openModal();
      };

      const confirmTerminate = () => {
          router.delete(route("member-groups.destroy", groupToTerminate.id), {
              onSuccess: () => {
                  confirmModal.closeModal();
                  toast.success("Grupa uspješno izbrisana.");
                  setGroupToTerminate(null);
              },
              onError: () => {
                  toast.error("Došlo je do pogreške prilikom brisanja.");
              },
          });
      };


      const handleClose = () => {
          setEditingGroup(null);
          closeModal();
      };
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
            <div className="max-w-full overflow-x-auto">
                <Table>
                    <TableHeader className="border-b border-gray-200 dark:border-white/[0.05] bg-gray-50">
                        <TableRow>
                            <TableCell
                                isHeader
                                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                            >
                                Naziv Grupe
                            </TableCell>
                            <TableCell
                                isHeader
                                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                            >
                                Opis
                            </TableCell>
                            <TableCell
                                isHeader
                                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                            >
                                Broj članova
                            </TableCell>
                            <TableCell
                                isHeader
                                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
                            >
                                Radionica
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
                        {data.map((group) => (
                            <TableRow key={group.id}>
                                <TableCell className="px-5 py-4 text-start text-theme-sm dark:text-white/90">
                                    {group.name}
                                </TableCell>
                                <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                    {group.description}
                                </TableCell>
                                <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                    {group.members_count}
                                </TableCell>
                                <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                    {group.workshop?.name ?? "—"}
                                </TableCell>
                                <TableCell className="px-5 py-4 text-start">
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => handleEdit(group)}
                                            className="hover:text-blue-600"
                                        >
                                            <Cog8ToothIcon className="h-5 w-5" />
                                        </button>
                                        <button
                                            onClick={() =>
                                                handleTerminate(group)
                                            }
                                            className="hover:text-red-700"
                                        >
                                            <TrashIcon className="h-5 w-5" />
                                        </button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
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
                    <ChevronLeftIcon className="h-4 w-4" />
                </button>

                {/* Page Summary for small screens */}
                <span className="block text-sm font-medium text-gray-700 dark:text-gray-400 sm:hidden">
                    Page {pagination.current_page} of {pagination.last_page}
                </span>

                {/* Page Numbers */}
                <ul className="hidden items-center gap-0.5 sm:flex">
                    {Array.from(
                        { length: pagination.last_page },
                        (_, i) => i + 1,
                    ).map((page) => (
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
                    ))}
                </ul>

                {/* Next Button */}
                <button
                    onClick={() =>
                        handlePageChange(pagination.current_page + 1)
                    }
                    disabled={pagination.current_page === pagination.last_page}
                    className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 sm:p-2.5 text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
                >
                    <ChevronRightIcon className="h-4 w-4" />
                </button>
            </div>
            <Modal
                isOpen={isOpen}
                onClose={handleClose}
                className="max-w-[700px] m-4"
            >
                <div className="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                    {editingGroup && (
                        <>
                            <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                                Uredi grupu
                            </h5>

                            <MemberGroupEditForm
                                group={editingGroup}
																workshops={workshops}
                                onClose={handleClose}
                            />
                        </>
                    )}
                </div>
            </Modal>

            <Modal
                isOpen={confirmModal.isOpen}
                onClose={confirmModal.closeModal}
                className="max-w-[600px] p-5 lg:p-10"
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
                        Brisanje grupe
                    </h4>
                    <p className="text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Jeste li sigurni da želite izbrisati grupu?
                    </p>

                    <div className="flex items-center justify-center w-full gap-3 mt-7">
                        <Button
                            onClick={confirmTerminate}
                            variant="primary"
                            size="sm"
                        >
                            Da, izbriši
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}
