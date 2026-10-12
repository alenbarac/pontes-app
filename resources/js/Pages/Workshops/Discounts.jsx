import { useState } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import { Modal } from "@/Components/ui/modal";
import { useModal } from "@/hooks/useModal";
import Button from "@/Components/ui/button/Button";
import Select from "@/Components/form/Select";
import WorkshopDiscountForm from "@/Components/Workshop/WorkshopDiscountForm";
import toast from "react-hot-toast";

function money(value) {
    return `${Number(value).toFixed(2)} EUR`;
}

export default function Discounts({
    workshops = [],
    basePlans = {},
    discounts = [],
    workshopId = "",
}) {
    const createModal = useModal();
    const editModal = useModal();
    const deleteModal = useModal();
    const [selected, setSelected] = useState(null);

    const openEdit = (discount) => {
        setSelected(discount);
        editModal.openModal();
    };

    const openDelete = (discount) => {
        setSelected(discount);
        deleteModal.openModal();
    };

    const closeEdit = () => {
        editModal.closeModal();
        setSelected(null);
    };

    const closeDelete = () => {
        deleteModal.closeModal();
        setSelected(null);
    };

    const handleFilter = (value) => {
        router.get(
            route("workshop-discounts.index"),
            value && value !== "all" ? { workshop_id: value } : {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const confirmDelete = () => {
        if (!selected) {
            return;
        }

        router.delete(route("workshop-discounts.destroy", selected.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Popust je obrisan.");
                closeDelete();
            },
            onError: (errors) => {
                toast.error(errors.plan || "Popust se ne može obrisati.");
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Popusti" />
            <Breadcrumb pageName="Popusti" />
            <ComponentCard
                title="Popusti"
                headerAction={
                    <Button
                        onClick={createModal.openModal}
                        className="inline-flex items-center justify-center gap-2 rounded-lg transition px-4 py-3 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
                    >
                        Novi popust
                    </Button>
                }
            >
                <div className="px-6 pb-4 max-w-sm">
                    <Select
                        value={workshopId || "all"}
                        onChange={handleFilter}
                        options={[
                            { value: "all", label: "Sve radionice" },
                            ...workshops.map((workshop) => ({
                                value: String(workshop.id),
                                label: workshop.name,
                            })),
                        ]}
                    />
                </div>

                {discounts.length === 0 ? (
                    <div className="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                        Nema popusta za prikaz
                    </div>
                ) : (
                    <div className="custom-scrollbar overflow-x-auto">
                        <table className="min-w-full">
                            <thead>
                                <tr className="bg-gray-50 dark:bg-gray-900">
                                    {[
                                        "Radionica",
                                        "Plan",
                                        "Popust",
                                        "Osnovni iznos",
                                        "Iznos s popustom",
                                        "Naplata",
                                        "Članovi",
                                        "",
                                    ].map((heading) => (
                                        <th
                                            key={heading || "actions"}
                                            className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {discounts.map((discount) => (
                                    <tr
                                        key={discount.id}
                                        className="border-t border-gray-100 dark:border-gray-800"
                                    >
                                        <td className="px-6 py-4 text-sm text-gray-800 dark:text-white/90">
                                            {discount.workshop_name}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-800 dark:text-white/90">
                                            {discount.plan}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-800 dark:text-white/90">
                                            {discount.percent}%
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-800 dark:text-white/90">
                                            {money(discount.base_amount)}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-800 dark:text-white/90">
                                            {money(discount.total_fee)}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-800 dark:text-white/90">
                                            {discount.billing_frequency}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-800 dark:text-white/90">
                                            {discount.members_count}
                                        </td>
                                        <td className="px-6 py-4 text-sm">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="xs"
                                                    onClick={() => openEdit(discount)}
                                                >
                                                    Uredi
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="xs"
                                                    onClick={() => openDelete(discount)}
                                                >
                                                    Obriši
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </ComponentCard>

            <Modal
                isOpen={createModal.isOpen}
                onClose={createModal.closeModal}
                className="max-w-[700px] m-4"
            >
                <div className="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                    <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                        Novi popust
                    </h5>
                    <WorkshopDiscountForm
                        workshops={workshops}
                        basePlans={basePlans}
                        onClose={createModal.closeModal}
                    />
                </div>
            </Modal>

            <Modal
                isOpen={editModal.isOpen}
                onClose={closeEdit}
                className="max-w-[700px] m-4"
            >
                <div className="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                    <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                        Uredi popust
                    </h5>
                    {selected && (
                        <WorkshopDiscountForm
                            workshops={workshops}
                            basePlans={basePlans}
                            discount={selected}
                            onClose={closeEdit}
                        />
                    )}
                </div>
            </Modal>

            <Modal
                isOpen={deleteModal.isOpen}
                onClose={closeDelete}
                className="max-w-[500px] p-5 lg:p-10"
            >
                <div className="text-center">
                    <h4 className="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                        Brisanje popusta
                    </h4>
                    <p className="text-sm leading-6 text-gray-500 dark:text-gray-400 mb-6">
                        Jeste li sigurni da želite obrisati popust{" "}
                        <strong>{selected?.plan}</strong>?
                    </p>
                    <div className="flex justify-center gap-3">
                        <Button type="button" variant="outline" onClick={closeDelete}>
                            Odustani
                        </Button>
                        <Button type="button" variant="primary" onClick={confirmDelete}>
                            Obriši
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
