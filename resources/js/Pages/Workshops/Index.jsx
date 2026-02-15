import React, { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import WorkshopCard from "@/Components/Workshop/WorkshopCard";
import { Modal } from "@/Components/ui/modal";
import { useModal } from "@/hooks/useModal";
import Button from "@/Components/ui/button/Button";
import WorkshopCreateForm from "@/Components/Workshop/WorkshopCreateForm";
import WorkshopEditForm from "@/Components/Workshop/WorkshopEditForm";
import WorkshopDeleteModal from "@/Components/Workshop/WorkshopDeleteModal";

export default function Index({ workshops }) {
    const createModal = useModal();
    const editModal = useModal();
    const deleteModal = useModal();
    const [selectedWorkshop, setSelectedWorkshop] = useState(null);

    const handleEdit = (workshop) => {
        setSelectedWorkshop(workshop);
        editModal.openModal();
    };

    const handleDelete = (workshop) => {
        setSelectedWorkshop(workshop);
        deleteModal.openModal();
    };

    const handleCloseEdit = () => {
        editModal.closeModal();
        setSelectedWorkshop(null);
    };

    const handleCloseDelete = () => {
        deleteModal.closeModal();
        setSelectedWorkshop(null);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Radionice" />
            <Breadcrumb pageName="Radionice" />
            <ComponentCard
                title="Popis Radionica"
                headerAction={
                    <Button
                        onClick={createModal.openModal}
                        className="inline-flex items-center justify-center gap-2 rounded-lg transition px-4 py-3 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
                    >
                        Nova radionica
                    </Button>
                }
            >
                {workshops.length === 0 ? (
                    <div className="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                        Nema radionica za prikaz
                    </div>
                ) : (
                    <div className="px-6 pb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            {workshops.map((workshop) => (
                                <WorkshopCard
                                    key={workshop.id}
                                    workshop={workshop}
                                    onEdit={handleEdit}
                                    onDelete={handleDelete}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </ComponentCard>

            {/* Create Modal */}
            <Modal
                isOpen={createModal.isOpen}
                onClose={createModal.closeModal}
                className="max-w-[700px] m-4"
            >
                <div className="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                    <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                        Nova radionica
                    </h5>
                    <WorkshopCreateForm onClose={createModal.closeModal} />
                </div>
            </Modal>

            {/* Edit Modal */}
            <Modal
                isOpen={editModal.isOpen}
                onClose={handleCloseEdit}
                className="max-w-[700px] m-4"
            >
                <div className="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                    <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                        Uredi radionicu
                    </h5>
                    {selectedWorkshop && (
                        <WorkshopEditForm
                            workshop={selectedWorkshop}
                            onClose={handleCloseEdit}
                        />
                    )}
                </div>
            </Modal>

            {/* Delete Modal */}
            <WorkshopDeleteModal
                isOpen={deleteModal.isOpen}
                onClose={handleCloseDelete}
                workshop={selectedWorkshop}
            />
        </AuthenticatedLayout>
    );
}
