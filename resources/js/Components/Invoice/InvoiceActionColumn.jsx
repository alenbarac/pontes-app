    import { Cog8ToothIcon, CogIcon, EllipsisVerticalIcon } from "@heroicons/react/24/outline";

    const InvoiceActionsDropdown = ({ invoice, onOpenDetails }) => {
        return (
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    onOpenDetails(invoice);
                }}
                className="p-1 rounded hover:bg-gray-100 dark:hover:bg-white/10"
                aria-label="Detalji računa"
            >
                <Cog8ToothIcon className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 size-6" />
            </button>
        );
    };

    export default InvoiceActionsDropdown;
