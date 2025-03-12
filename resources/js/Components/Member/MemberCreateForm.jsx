import React, { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import toast from "react-hot-toast";
import DatePicker from "../DatePicker";

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

        // ✅ Client-side validation
        const validationErrors = {};
        if (!data.first_name.trim())
            validationErrors.first_name = "Ime je obavezno.";
        if (!data.last_name.trim())
            validationErrors.last_name = "Prezime je obavezno.";
        if (!data.phone_number.trim())
            validationErrors.phone_number = "Broj telefona je obavezan.";
        if (!data.email.trim()) validationErrors.email = "Email je obavezan.";
        if (!data.workshop_id)
            validationErrors.workshop_id = "Radionica je obavezna.";

        if (Object.keys(validationErrors).length > 0) {
            // ✅ Display errors without submitting
            toast.error("Molimo ispunite sva obavezna polja.");
            return;
        }

        post(route("members.store"), {
            data,
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
                    Radionice
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
                        Grupe
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

            <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                <div className="w-full xl:w-1/3">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Ime
                    </label>
                    <input
                        type="text"
                        placeholder="unos imena"
                        className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                        value={data.first_name}
                        onChange={(e) => setData("first_name", e.target.value)}
                    />
                    {errors.first_name && (
                        <p className="text-red-500 text-sm">
                            {errors.first_name}
                        </p>
                    )}
                </div>

                <div className="w-full xl:w-1/3">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Prezime
                    </label>
                    <input
                        type="text"
                        placeholder="unos prezimena"
                        className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                        value={data.last_name}
                        onChange={(e) => setData("last_name", e.target.value)}
                    />
                    {errors.first_name && (
                        <p className="text-red-500 text-sm">
                            {errors.last_name}
                        </p>
                    )}
                </div>

                <div className="w-full xl:w-1/3">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Email
                    </label>
                    <input
                        type="email"
                        placeholder="email polaznika/ce"
                        className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                    />
                    {errors.email && (
                        <p className="text-red-500 text-sm">{errors.email}</p>
                    )}
                </div>
            </div>

            <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                <div className="w-full xl:w-1/2">
                    <DatePicker
                        value={data.date_of_birth}
                        onChange={(date) => setData("date_of_birth", date)}
                        placeholder="odabir datuma"
                        dateFormat="Y-m-d"
                        labelText="Datum rođenja"
                    />
                </div>

                <div className="w-full xl:w-1/2">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Telefon
                    </label>
                    <input
                        type="text"
                        placeholder="br. telefon/mobitel"
                        className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                        value={data.phone_number}
                        onChange={(e) =>
                            setData("phone_number", e.target.value)
                        }
                    />
                    {errors.phone_number && (
                        <p className="text-red-500 text-sm">
                            {errors.phone_number}
                        </p>
                    )}
                </div>
            </div>

            <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                <div className="w-full xl:w-1/2">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Kontakt roditelja
                    </label>
                    <input
                        type="text"
                        placeholder="telefon/mobitel roditelja"
                        className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                        value={data.parent_contact}
                        onChange={(e) =>
                            setData("parent_contact", e.target.value)
                        }
                    />
                    {errors.parent_contact && (
                        <p className="text-red-500 text-sm">
                            {errors.parent_contact}
                        </p>
                    )}
                </div>

                <div className="w-full xl:w-1/2">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Email roditelja
                    </label>
                    <input
                        type="email"
                        placeholder="email adresa"
                        className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                        value={data.parent_email}
                        onChange={(e) =>
                            setData("parent_email", e.target.value)
                        }
                    />
                    {errors.parent_email && (
                        <p className="text-red-500 text-sm">
                            {errors.parent_email}
                        </p>
                    )}
                </div>
            </div>

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
