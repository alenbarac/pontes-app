import React from "react";
import { Link, router, useForm } from "@inertiajs/react";
import toast from "react-hot-toast";
import Label from "@/Components/form/Label";
import Input from "@/Components/form/input/InputField";
import Form from "@/Components/form/Form";
import Button from "@/Components/ui/button/Button";
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/themes/light.css";
import { CalendarDaysIcon } from "@heroicons/react/24/outline";
import Switch from "../form/switch/Switch";

export default function MemberDetailEditForm({ member, closeModal }) {
    const { data, setData, put, processing, errors } = useForm({
        is_active: member.is_active || false,
        first_name: member.first_name || "",
        last_name: member.last_name || "",
        date_of_birth: member.date_of_birth || "",
        phone_number: member.phone_number || "",
        email: member.email || "",
        parent_contact: member.parent_contact || "",
        parent_email: member.parent_email || "",
    });


    const handleSubmit = (e) => {
        console.log("Submitting form with data:", data);
        e.preventDefault();
        
        put(route("members.update", member.id), {
            data,
            preserveState: true,
            onSuccess: () => {
                closeModal();
                toast.success("Član je uspješno ažuriran!");
            },
            onError: (err) => {
                console.log(err);
                toast.error(
                    err.email || "Došlo je do greške. Pokušajte ponovno.",
                );
            },
        });
    };



    return (
        <form onSubmit={handleSubmit}>
            <>
                <div className="my-3">
                    <Switch
                        label="Aktivan član"
                        checked={data.is_active}
                        onChange={(checked) => setData("is_active", checked)}
                    />
                </div>

                <div className="grid gap-6 sm:grid-cols-2 mb-4">
                    {/* First Name */}
                    <div>
                        <Label htmlFor="first_name">Ime</Label>
                        <Input
                            type="text"
                            id="first_name"
                            placeholder="Unesite ime"
                            value={data.first_name}
                            onChange={(e) =>
                                setData("first_name", e.target.value)
                            }
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
                            onChange={(e) =>
                                setData("last_name", e.target.value)
                            }
                        />
                        {errors.last_name && (
                            <p className="text-red-500 text-sm">
                                {errors.last_name}
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 sm:grid-cols-2 mb-4">
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
                            <p className="text-red-500 text-sm">
                                {errors.email}
                            </p>
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
                </div>

                <div className="grid gap-6 sm:grid-cols-1 mb-4">
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

                <div className="grid gap-6 sm:grid-cols-2 mb-4">
                    {/* Parent Contact */}
                    <div>
                        <Label htmlFor="parent_contact">
                            Kontakt roditelja
                        </Label>
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

                    {/* Parent Email */}
                    <div>
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
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <Button size="sm" disabled={processing}>
                        {processing ? "Spremam..." : "Spremi promjene"}
                    </Button>
                    <Button variant="outline" onClick={closeModal}>
                        Odustani
                    </Button>
                </div>
            </>
        </form>
    );
}
