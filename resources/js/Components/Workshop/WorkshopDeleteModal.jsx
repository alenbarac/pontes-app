import { Modal } from "@/Components/ui/modal";
import Button from "@/ui/button/Button";
import { router } from "@inertiajs/react";
import toast from "react-hot-toast";

export default function WorkshopDeleteModal({ isOpen, onClose, workshop }) {
    if (!workshop) return null;

    const handleDeleteConfirm = () => {
        router.delete(route("workshops.destroy", workshop.id), {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                toast.success("Radionica je uspješno obrisana.");
            },
            onError: () => {
                toast.error("Došlo je do pogreške pri brisanju radionice.");
            },
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
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
                            width="40"
                            height="40"
                            viewBox="0 0 40 40"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M20 13.3333V20M20 26.6667H20.0167M28.3333 33.3333H11.6667C10.7826 33.3333 9.93474 32.9821 9.30964 32.357C8.68453 31.7319 8.33334 30.8841 8.33334 30V13.3333C8.33334 12.4493 8.68453 11.6014 9.30964 10.9763C9.93474 10.3512 10.7826 10 11.6667 10H22.3333L31.6667 19.3333V30C31.6667 30.8841 31.3155 31.7319 30.6904 32.357C30.0653 32.9821 29.2174 33.3333 28.3333 33.3333Z"
                                stroke="#F87171"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    </span>
                </div>

                <h4 className="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90 sm:text-title-sm">
                    Brisanje radionice
                </h4>
                <p className="text-sm leading-6 text-gray-500 dark:text-gray-400 mb-4">
                    Jeste li sigurni da želite obrisati radionicu{" "}
                    <strong>{workshop.name}</strong>?
                </p>
                <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4 text-left">
                    <p className="text-sm font-semibold text-red-800 dark:text-red-300 mb-2">
                        ⚠️ UPOZORENJE: Ova akcija je nepovratna!
                    </p>
                    <ul className="text-xs text-red-700 dark:text-red-400 space-y-1 list-disc list-inside">
                        <li>Svi upisi članova u ovu radionicu biti će obrisani</li>
                        <li>Sve grupe povezane s ovom radionicom biti će obrisane</li>
                        <li>Svi planovi članarina za ovu radionicu biti će obrisani</li>
                        <li>Svi podaci vezani uz ovu radionicu biti će trajno izgubljeni</li>
                    </ul>
                </div>
                <div className="flex items-center justify-center w-full gap-3 mt-7">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        Odustani
                    </button>
                    <button
                        onClick={handleDeleteConfirm}
                        className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition"
                    >
                        Da, obriši
                    </button>
                </div>
            </div>
        </Modal>
    );
}
