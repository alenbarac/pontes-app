import React from "react";
import { Head, Link, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import MemberGroupTable from "@/Components/MemberGroup/MemberGroupTable";
import { Modal } from "@/Components/ui/modal";
import { useModal } from "@/hooks/useModal";
import Button from "@/Components/ui/button/Button";
import MemberGroupCreateForm from "@/Components/MemberGroup/MemberGroupAddForm";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/24/outline";

export default function Index({ groups, workshops }) {
    const createModal = useModal();

    const pagination = groups.meta;

    const handlePageChange = (page) => {
        if (page !== pagination.current_page) {
            router.get(
                route("member-groups.index"),
                { page },
                { preserveScroll: true },
            );
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Grupe" />
            <Breadcrumb pageName="Grupe" />
            <ComponentCard
                title="Popis Grupa"
                headerAction={
                    <Button
                        onClick={createModal.openModal}
                        className="inline-flex items-center justify-center gap-2 rounded-lg transition px-4 py-3 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
                    >
                        Nova grupa
                    </Button>
                }
            >
                <MemberGroupTable groups={groups} workshops={workshops} />
            </ComponentCard>

            <Modal
                isOpen={createModal.isOpen}
                onClose={createModal.closeModal}
                className="max-w-[700px] m-4"
            >
                <div className="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                    <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                        Nova grupa
                    </h5>
                    <MemberGroupCreateForm
                        onClose={createModal.closeModal}
                        workshops={workshops}
                    />
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
