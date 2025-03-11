import React, { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import toast from "react-hot-toast";

const MemberCreateForm = ({ workshops, groups, membershipPlans }) => {
    const { data, setData, post, processing, errors } = useForm({
        first_name: "",
        last_name: "",
        date_of_birth: "",
        phone_number: "",
        email: "",
        is_active: true,
        parent_contact: "",
        parent_email: "",
        workshop_id: "",
        group_id: "",
        membership_plan_id: "",
    });

    const [filteredGroups, setFilteredGroups] = useState([]);
    const [filteredPlans, setFilteredPlans] = useState([]);

    // ✅ Update Group & Membership Plan Dropdowns when Workshop is Selected
    const handleWorkshopSelection = (workshopId) => {
        setData("workshop_id", workshopId);

        // ✅ Fetch all available groups per workshop
        setFilteredGroups(groups[workshopId] || []);

        // ✅ Fetch all available membership plans per workshop
        setFilteredPlans(membershipPlans[workshopId] || []);

        // Reset dependent selections
        setData("group_id", "");
        setData("membership_plan_id", "");
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        post(route("members.store"), {
            onSuccess: () => {
                toast.success("Član je uspješno dodan!");
                router.visit(route("members.index"));
            },
            onError: (errors) => {
                toast.error(
                    errors.email || "Došlo je do greške. Pokušajte ponovno.",
                );
            },
        });
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="rounded-md bg-white shadow-md dark:bg-boxdark p-6"
        >
            {/* Workshop Selection */}
            <div className="mb-5">
                <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                    Radionica
                </label>
                <select
                    value={data.workshop_id}
                    onChange={(e) => handleWorkshopSelection(e.target.value)}
                    className="w-full rounded border border-stroke bg-transparent px-5 py-3 text-black outline-none transition focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                >
                    <option value="">Odaberi radionicu</option>
                    {workshops.map((workshop) => (
                        <option key={workshop.id} value={workshop.id}>
                            {workshop.name}
                        </option>
                    ))}
                </select>
                {errors.workshop_id && (
                    <p className="text-red-500 text-sm">{errors.workshop_id}</p>
                )}
            </div>

            {/* Group Selection (Single Selection) */}
            {filteredGroups.length > 0 && (
                <div className="mb-5">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Grupa u radionici
                    </label>
                    <select
                        value={data.group_id}
                        onChange={(e) => setData("group_id", e.target.value)}
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 text-black outline-none transition focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                    >
                        <option value="">Odaberi grupu</option>
                        {filteredGroups.map((group) => (
                            <option key={group.id} value={group.id}>
                                {group.name}
                            </option>
                        ))}
                    </select>
                    {errors.group_id && (
                        <p className="text-red-500 text-sm">
                            {errors.group_id}
                        </p>
                    )}
                </div>
            )}

            {/* Membership Plan Selection (Single Selection) */}
            {filteredPlans.length > 0 && (
                <div className="mb-5">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Članarina
                    </label>
                    <select
                        value={data.membership_plan_id}
                        onChange={(e) =>
                            setData("membership_plan_id", e.target.value)
                        }
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 text-black outline-none transition focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                    >
                        <option value="">Odaberi plan</option>
                        {filteredPlans.map((plan) => (
                            <option key={plan.id} value={plan.id}>
                                {plan.plan} -{" "}
                                {parseFloat(plan.total_fee).toFixed(2)} kn
                            </option>
                        ))}
                    </select>
                    {errors.membership_plan_id && (
                        <p className="text-red-500 text-sm">
                            {errors.membership_plan_id}
                        </p>
                    )}
                </div>
            )}

            {/* Submit Button */}
            <div className="mt-6">
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded bg-primary p-3 text-white"
                >
                    {processing ? "Spremam..." : "Spremi upis"}
                </button>
            </div>
        </form>
    );
};

export default MemberCreateForm;
