import React, { useState } from "react";
import { useForm, router } from "@inertiajs/react";
import toast from "react-hot-toast";
import DatePicker from "../DatePicker";

const MemberEditForm = ({ member, workshops, groups, membershipPlans }) => {
   const { data, setData, put, processing, errors } = useForm({
       first_name: member.first_name,
       last_name: member.last_name,
       date_of_birth: member.date_of_birth,
       phone_number: member.phone_number,
       email: member.email,
       is_active: member.is_active,
       parent_contact: member.parent_contact,
       parent_email: member.parent_email,
       invoice_email: member.invoice_email,
       currentEnrollment: {
           workshop_id: member.workshops[0]?.id || "",
           // Force the pivot value to a string, or fallback to member.memberships[0].id if needed.
           membership_plan_id: member.workshops[0]?.pivot?.membership_plan_id
               ? member.workshops[0].pivot.membership_plan_id.toString()
               : member.memberships?.[0]?.id?.toString() || "",
           group_id: member.workshopGroups?.[0]?.member_group_id || "",
       },
       newEnrollment: {
           workshop_id: "",
           group_id: "",
           membership_plan_id: "",
       },
   });


   console.log("Member Workshops:", member.workshops);
   console.log("Member Memberships:", member.memberships);
   console.log("Member WorkshopGroups:", member.workshopGroups);
    // For optional new enrollment: filtered groups and plans after selecting a new workshop.
    const [newEnrollmentGroups, setNewEnrollmentGroups] = useState([]);
    const [newEnrollmentPlans, setNewEnrollmentPlans] = useState([]);

    // For current enrollment, compute available groups and membership plans based on the workshop.
    const currentEnrollmentGroups = Object.values(groups)
        .flat()
        .filter(
            (g) => g.workshop_id === Number(data.currentEnrollment.workshop_id),
        );
    const currentEnrollmentPlans = Object.values(membershipPlans)
        .flat()
        .filter(
            (p) => p.workshop_id === Number(data.currentEnrollment.workshop_id),
        );

    // For new enrollment: show only workshops that are not already enrolled.
    const availableWorkshops = workshops.filter(
        (w) => !member.workshops.some((mw) => mw.id === w.id),
    );

    const handleNewEnrollmentWorkshopSelection = (workshopIdStr) => {
        const workshopId = Number(workshopIdStr);
        setData("newEnrollment", {
            ...data.newEnrollment,
            workshop_id: workshopId,
            group_id: "",
            membership_plan_id: "",
        });
        // Filter groups and plans using numeric comparison.
        setNewEnrollmentGroups(
            Object.values(groups)
                .flat()
                .filter((g) => g.workshop_id === workshopId),
        );
        setNewEnrollmentPlans(
            Object.values(membershipPlans)
                .flat()
                .filter((p) => p.workshop_id === workshopId),
        );
    };

    // Check if current enrollment workshop is Dramska 60+
    const currentWorkshop = workshops.find(
        (w) => w.id === data.currentEnrollment.workshop_id,
    );
    const isDramska60 = currentWorkshop?.name?.includes('60+');

    const handleSubmit = (e) => {
        e.preventDefault();

        put(route("members.update", member.id), {
            data,
            onSuccess: () => {
                toast.success("Član je uspješno ažuriran!");
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
            <h2 className="text-xl font-semibold mb-4">Uredi člana</h2>

            {/* Basic Member Fields */}
            <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                {/* First ROW */}
                <div className="w-full xl:w-1/3">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Ime
                    </label>
                    <input
                        type="text"
                        placeholder="unos imena"
                        className="w-full rounded border px-5 py-3"
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
                        className="w-full rounded border px-5 py-3"
                        value={data.last_name}
                        onChange={(e) => setData("last_name", e.target.value)}
                    />
                    {errors.last_name && (
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
                        className="w-full rounded border px-5 py-3"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                    />
                    {errors.email && (
                        <p className="text-red-500 text-sm">{errors.email}</p>
                    )}
                </div>
            </div>

            {/* Second ROW */}
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
                        className="w-full rounded border px-5 py-3"
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

            {/* Third ROW */}
            <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                {/* Parent Contact - Hidden for Dramska 60+ */}
                {!isDramska60 && (
                    <div className="w-full xl:w-1/2">
                        <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                            Kontakt roditelja
                        </label>
                        <input
                            type="text"
                            placeholder="telefon/mobitel roditelja"
                            className="w-full rounded border px-5 py-3"
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
                )}
                {/* Parent Email - Hidden for Dramska 60+ */}
                {!isDramska60 && (
                    <div className="w-full xl:w-1/2">
                        <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                            Email roditelja
                        </label>
                        <input
                            type="email"
                            placeholder="email adresa"
                            className="w-full rounded border px-5 py-3"
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
                )}

                <div className="w-full xl:w-1/2">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Email za račune
                    </label>
                    <input
                        type="email"
                        placeholder="email za račune"
                        className="w-full rounded border px-5 py-3"
                        value={data.invoice_email}
                        onChange={(e) =>
                            setData("invoice_email", e.target.value)
                        }
                    />
                    {errors.invoice_email && (
                        <p className="text-red-500 text-sm">
                            {errors.invoice_email}
                        </p>
                    )}
                </div>
            </div>

            {/* Current Enrollment Section */}
            <div className="mb-5">
                <h3 className="text-lg font-medium mb-2">
                    Trenutna radionica i članarina
                </h3>
                {data.currentEnrollment.workshop_id ? (
                    <div className="flex flex-col gap-1 border-b pb-2">
                        <span className="font-semibold">
                            {
                                workshops.find(
                                    (w) =>
                                        w.id ===
                                        data.currentEnrollment.workshop_id,
                                )?.name
                            }
                        </span>
                        {/* Current Group Selection */}
                        <div className="flex flex-col gap-1">
                            <label className="text-sm">Grupa:</label>
                            <select
                                value={data.currentEnrollment.group_id}
                                onChange={(e) =>
                                    setData("currentEnrollment", {
                                        ...data.currentEnrollment,
                                        group_id: e.target.value,
                                    })
                                }
                                className="w-full rounded border px-5 py-3"
                            >
                                <option value="">Odaberi grupu</option>
                                {currentEnrollmentGroups.map((group) => (
                                    <option key={group.id} value={group.id}>
                                        {group.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {/* Current Membership Plan Selection */}
                        <div className="flex flex-col gap-1">
                            <label className="text-sm">Članarina:</label>
                            <select
                                value={
                                    data.currentEnrollment.membership_plan_id
                                }
                                onChange={(e) =>
                                    setData("currentEnrollment", {
                                        ...data.currentEnrollment,
                                        membership_plan_id: e.target.value,
                                    })
                                }
                                className="w-full rounded border px-5 py-3"
                            >
                                <option value="">Odaberi članarinu</option>
                                {currentEnrollmentPlans.map((plan) => (
                                    <option key={plan.id} value={plan.id}>
                                        {plan.plan} -{" "}
                                        {parseFloat(plan.total_fee).toFixed(2)}{" "}
                                        kn
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                ) : (
                    <p className="text-gray-500">Nema postojećih radionica</p>
                )}
            </div>

            {/* Optional New Enrollment Section */}
            <div className="mb-5">
                <h3 className="text-lg font-medium mb-2">
                    Dodaj novu radionicu i članarinu (opcionalno)
                </h3>
                {/* New Workshop Selection */}
                <div className="mb-5">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Radionica
                    </label>
                    <select
                        value={data.newEnrollment.workshop_id || ""}
                        onChange={(e) =>
                            handleNewEnrollmentWorkshopSelection(e.target.value)
                        }
                        className="w-full rounded border px-5 py-3"
                    >
                        <option value="">Odaberi radionicu</option>
                        {availableWorkshops.map((workshop) => (
                            <option key={workshop.id} value={workshop.id}>
                                {workshop.name}
                            </option>
                        ))}
                    </select>
                </div>
                {/* New Group and Membership Plan (side by side) */}
                {data.newEnrollment.workshop_id && (
                    <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                        <div className="w-full xl:w-1/2">
                            <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                                Grupa
                            </label>
                            <select
                                value={data.newEnrollment.group_id || ""}
                                onChange={(e) =>
                                    setData("newEnrollment", {
                                        ...data.newEnrollment,
                                        group_id: e.target.value,
                                    })
                                }
                                className="w-full rounded border px-5 py-3"
                            >
                                <option value="">Odaberi grupu</option>
                                {newEnrollmentGroups.map((group) => (
                                    <option key={group.id} value={group.id}>
                                        {group.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="w-full xl:w-1/2">
                            <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                                Članarina
                            </label>
                            <select
                                value={
                                    data.newEnrollment.membership_plan_id || ""
                                }
                                onChange={(e) =>
                                    setData("newEnrollment", {
                                        ...data.newEnrollment,
                                        membership_plan_id: e.target.value,
                                    })
                                }
                                className="w-full rounded border px-5 py-3"
                            >
                                <option value="">Odaberi članarinu</option>
                                {newEnrollmentPlans.map((plan) => (
                                    <option key={plan.id} value={plan.id}>
                                        {plan.plan} -{" "}
                                        {parseFloat(plan.total_fee).toFixed(2)}{" "}
                                        kn
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}
            </div>

            {/* Submit Button */}
            <div className="mt-6">
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded bg-primary p-3 text-white"
                >
                    {processing ? "Spremam..." : "Spremi promjene"}
                </button>
            </div>
        </form>
    );
};

export default MemberEditForm;
