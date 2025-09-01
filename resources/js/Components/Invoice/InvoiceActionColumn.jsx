    import { useState } from "react";
    import { EllipsisVerticalIcon } from "@heroicons/react/24/outline";
    import { Dropdown } from "@/Components/ui/dropdown/Dropdown";
    import { DropdownItem } from "@/Components/ui/dropdown/DropdownItem";
    import { router } from "@inertiajs/react";
    import toast from "react-hot-toast"; // Make sure this is imported

    const InvoiceActionsDropdown = ({ invoiceId, isPaid = false }) => {
        const [isOpen, setIsOpen] = useState(false);

        const toggleDropdown = () => setIsOpen(!isOpen);
        const closeDropdown = () => setIsOpen(false);

        const updateStatus = (status, successMessage) => {
            closeDropdown();
            router.patch(
                route("invoices.updateStatus", invoiceId),
                { status },
                {
                    preserveScroll: true,
                    onSuccess: () => toast.success(successMessage),
                },
            );
        };

        const markAsPaid = () => {
            closeDropdown();
            router.patch(
                route("invoices.markPaid", invoiceId),
                {}, // no payload needed; controller will use amount_due
                {
                    preserveScroll: true,
                    onSuccess: () => toast.success("Račun označen kao plaćen."),
                },
            );
        };

        return (
            <div className="relative inline-block">
                <button className="dropdown-toggle" onClick={toggleDropdown}>
                    <EllipsisVerticalIcon className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 size-6" />
                </button>
                <Dropdown
                    isOpen={isOpen}
                    onClose={closeDropdown}
                    className="w-40 p-2"
                >
                    {!isPaid && (
                        <DropdownItem onItemClick={markAsPaid}>
                            Plaćeno
                        </DropdownItem>
                    )}
                    <DropdownItem
                        onItemClick={() =>
                            updateStatus(
                                "Otvoreno",
                                'Račun vraćen u status "Otvoreno".',
                            )
                        }
                    >
                        Otvoreno
                    </DropdownItem>
                </Dropdown>
            </div>
        );
    };

    export default InvoiceActionsDropdown;
