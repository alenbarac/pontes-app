import React, { useState } from "react";
import { Link, router, useForm } from "@inertiajs/react";
import toast from "react-hot-toast";
import Label from "@/Components/form/Label";
import Input from "@/Components/form/input/InputField";
import Select from "@/Components/form/Select";
import Button from "@/Components/ui/button/Button";
import Flatpickr from "react-flatpickr";

import "flatpickr/dist/themes/light.css";
import Form from "@/Components/form/Form";
import { CalendarDaysIcon } from "@heroicons/react/24/outline";

export default function MemberCreateForm({
    workshops,
    groups,
    membershipPlans,
}) {
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
        membership_start_date: "",
    });

    const [filteredGroups, setFilteredGroups] = useState([]);
    const [filteredPlans, setFilteredPlans] = useState([]);

    const handleWorkshopSelection = (workshopId) => {
        setData("workshop_id", workshopId);
        setFilteredGroups(groups[workshopId] || []);
        setFilteredPlans(membershipPlans[workshopId] || []);
        setData("group_id", "");
        setData("membership_plan_id", "");
    };

    // Check if selected workshop is Dramska 60+
    const selectedWorkshop = workshops.find(w => String(w.id) === String(data.workshop_id));
    const isDramska60 = selectedWorkshop?.name?.includes('60+');

    const handleSubmit = (e) => {
        e.preventDefault();

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
        if (!data.membership_start_date)
            validationErrors.membership_plan_start_date =
                "Obvezan unos početka upisa.";

        if (Object.keys(validationErrors).length > 0) {
            toast.error("Molimo ispunite sva obavezna polja.");
            return;
        }

        post(route("members.store"), {
            data,
            onSuccess: () => {
                router.visit(route("members.index"));
                toast.success("Član je uspješno dodan!");
            },
            onError: (err) => {
                toast.error(
                    err.email || "Došlo je do greške. Pokušajte ponovno.",
                );
            },
        });
    };

    return (
        <Form onSubmit={handleSubmit}>
            <div className="grid gap-6 sm:grid-cols-2 mb-4">
                {/* First Name */}
                <div>
                    <Label htmlFor="first_name">Ime</Label>
                    <Input
                        type="text"
                        id="first_name"
                        placeholder="Unesite ime"
                        value={data.first_name}
                        onChange={(e) => setData("first_name", e.target.value)}
                    />
                    {errors.first_name && (
                        <p className="text-red-500 text-sm">
                            {errors.first_name}
                        </p>
                    )}
                </div>

                {/* Last Name */}
                <div>
                    <Label htmlFor="last_name">Prezime</Label>
                    <Input
                        type="text"
                        id="last_name"
                        placeholder="Unesite prezime"
                        value={data.last_name}
                        onChange={(e) => setData("last_name", e.target.value)}
                    />
                    {errors.last_name && (
                        <p className="text-red-500 text-sm">
                            {errors.last_name}
                        </p>
                    )}
                </div>
            </div>

            <div className="grid gap-6 sm:grid-cols-3 mb-4">
                {/* Email */}
                <div>
                    <Label htmlFor="email">Email</Label>
                    <Input
                        type="email"
                        id="email"
                        placeholder="Email polaznika/ce"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                    />
                    {errors.email && (
                        <p className="text-red-500 text-sm">{errors.email}</p>
                    )}
                </div>

                {/* Phone Number */}
                <div>
                    <Label htmlFor="phone_number">Telefon</Label>
                    <Input
                        type="text"
                        id="phone_number"
                        placeholder="Broj telefona/mobitel"
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

                {/* Date of Birth */}
                <div>
                    <Label htmlFor="date_of_birth">Datum rođenja</Label>
                    <div className="relative">
                        <Flatpickr
                            value={data.date_of_birth}
                            onChange={(selectedDates, dateStr) =>
                                setData("date_of_birth", dateStr)
                            }
                            options={{ dateFormat: "Y-m-d" }}
                            className="w-full py-[10px] pl-3 pr-10 text-sm border border-gray-300 rounded-md focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                        />
                        <span className="absolute text-gray-500 -translate-y-1/2 pointer-events-none right-3 top-1/2 dark:text-gray-400">
                            <CalendarDaysIcon width={18} height={18} />
                        </span>
                    </div>
                </div>
            </div>

            <div className="mb-4">
                {/* Workshop Selection */}
                <div className="col-span-full mb-4">
                    <Label htmlFor="workshop_id">Radionica</Label>
                    <Select
                        id="workshop_id"
                        placeholder="Odaberi radionicu"
                        value={data.workshop_id}
                        onChange={(value) => handleWorkshopSelection(value)}
                        options={workshops.map((workshop) => ({
                            value: workshop.id,
                            label: workshop.name,
                        }))}
                    />
                    {errors.workshop_id && (
                        <p className="text-red-500 text-sm">
                            {errors.workshop_id}
                        </p>
                    )}
                </div>

                {/* Group Selection */}
                {filteredGroups.length > 0 && (
                    <div className="col-span-full mb-4">
                        <Label htmlFor="group_id">Grupe</Label>
                        <Select
                            id="group_id"
                            placeholder="Odaberi grupu"
                            value={data.group_id}
                            onChange={(value) => setData("group_id", value)}
                            options={filteredGroups.map((group) => ({
                                value: group.id,
                                label: group.name,
                            }))}
                        />
                        {errors.group_id && (
                            <p className="text-red-500 text-sm">
                                {errors.group_id}
                            </p>
                        )}
                    </div>
                )}

                {/* Membership Plan Selection */}
                {filteredPlans.length > 0 && (
                    <div className="grid gap-6 sm:grid-cols-2 mb-4">
                        <div className=" mb-4">
                            <Label htmlFor="membership_plan_id">
                                Članarina
                            </Label>
                            <Select
                                id="membership_plan_id"
                                placeholder="Odaberi plan"
                                value={data.membership_plan_id}
                                onChange={(value) =>
                                    setData("membership_plan_id", value)
                                }
                                options={filteredPlans.map((plan) => ({
                                    value: plan.id,
                                    label: `${plan.plan} - ${parseFloat(plan.total_fee).toFixed(2)} EUR`,
                                }))}
                            />
                            {errors.membership_plan_id && (
                                <p className="text-red-500 text-sm">
                                    {errors.membership_plan_id}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="membership_start_date">
                                Početak upisa
                            </Label>
                            <div className="relative">
                                <Flatpickr
                                    value={data.membership_start_date}
                                    onChange={(selectedDates, dateStr) =>
                                        setData(
                                            "membership_start_date",
                                            dateStr,
                                        )
                                    }
                                    options={{ dateFormat: "Y-m-d" }}
                                    className="w-full py-[10px] pl-3 pr-10 text-sm border border-gray-300 rounded-md focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                />
                                <span className="absolute text-gray-500 -translate-y-1/2 pointer-events-none right-3 top-1/2 dark:text-gray-400">
                                    <CalendarDaysIcon width={18} height={18} />
                                </span>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <div className="grid gap-6 sm:grid-cols-2">
                {/* Parent Contact - Hidden for Dramska 60+ */}
                {!isDramska60 && (
                    <div className="sm:col-span-1">
                        <Label htmlFor="parent_contact">Kontakt roditelja</Label>
                        <Input
                            type="text"
                            id="parent_contact"
                            placeholder="Telefon roditelja"
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
                    <div className="sm:col-span-1">
                        <Label htmlFor="parent_email">Email roditelja</Label>
                        <Input
                            type="email"
                            id="parent_email"
                            placeholder="Email roditelja"
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
                {/* Invoice Email */}
                <div className="sm:col-span-1">
                    <Label htmlFor="invoice_email">Email za račune</Label>
                    <Input
                        type="email"
                        id="invoice_email"
                        placeholder="Email za račune"
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

            <div className="mt-6 flex justify-end gap-3">
                <Button disabled={processing} size="sm">
                    {processing ? "Spremam..." : "Spremi upis"}
                </Button>
                <Link
                    className="inline-flex items-center justify-center gap-2 rounded-lg transition bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300 px-4 py-3 text-sm"
                    href={route("members.index")}
                >
                    Odustani
                </Link>
            </div>
        </Form>
    );
}
