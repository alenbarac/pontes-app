import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { Modal } from "@/Components/ui/modal";
import Button from "@/Components/ui/button/Button";
import toast from "react-hot-toast";

export default function BulkReassignModal({
    isOpen,
    onClose,
    selectedMembers,
    members,
    currentGroup,
    otherGroups,
    workshopId,
    onSuccess,
}) {
    const [targetGroupId, setTargetGroupId] = useState("");
    const [processing, setProcessing] = useState(false);

    const selectedMembersData = members.filter((m) =>
        selectedMembers.includes(m.id)
    );

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!targetGroupId) {
            toast.error("Molimo odaberite ciljnu grupu.");
            return;
        }

        if (!workshopId) {
            toast.error("Radionica nije definirana.");
            return;
        }

        setProcessing(true);

        router.post(
            route("member-groups.bulk-reassign", currentGroup.id),
            {
                member_ids: selectedMembers,
                target_group_id: targetGroupId,
                workshop_id: workshopId,
            },
            {
                onSuccess: () => {
                    toast.success("Članovi su uspješno premješteni u novu grupu.");
                    setTargetGroupId("");
                    setProcessing(false);
                    onSuccess();
                },
                onError: (errors) => {
                    const errorMessage =
                        errors.target_group_id ||
                        errors.member_ids ||
                        "Došlo je do pogreške prilikom premještanja.";
                    toast.error(errorMessage);
                    setProcessing(false);
                },
            }
        );
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            className="max-w-[600px] m-4"
        >
            <div className="no-scrollbar relative w-full max-w-[600px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                    Premjesti članove u drugu grupu
                </h5>

                <div className="mb-6">
                    <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Odabrano je <strong>{selectedMembers.length}</strong>{" "}
                        članova za premještanje:
                    </p>
                    <div className="max-h-32 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-800">
                        <ul className="space-y-1">
                            {selectedMembersData.map((member) => (
                                <li
                                    key={member.id}
                                    className="text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {member.first_name} {member.last_name}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label
                            htmlFor="target_group"
                            className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Ciljna grupa
                        </label>
                        <select
                            id="target_group"
                            value={targetGroupId}
                            onChange={(e) => setTargetGroupId(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            required
                        >
                            <option value="">Odaberite grupu...</option>
                            {otherGroups.map((group) => (
                                <option key={group.id} value={group.id}>
                                    {group.name}
                                </option>
                            ))}
                        </select>
                        {otherGroups.length === 0 && (
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Nema drugih grupa u ovoj radionici.
                            </p>
                        )}
                    </div>

                    <div className="flex items-center justify-end gap-3 mt-6">
                        <Button
                            type="button"
                            onClick={onClose}
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            Odustani
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            size="sm"
                            disabled={processing || !targetGroupId}
                        >
                            {processing ? "Premještanje..." : "Premjesti"}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
