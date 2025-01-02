import React from "react";
import { Head, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        first_name: "",
        last_name: "",
        birth_year: "",
        phone_number: "",
        email: "",
        is_active: true,
        parent_contact: "",
        parent_email: "",
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("members.store"));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <Breadcrumb pageName="Novi upis" />

            <div className="flex flex-col gap-9">
                <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
                    <div className="border-b border-stroke px-6.5 py-4 dark:border-strokedark">
                        <h3 className="font-medium text-black dark:text-white">
                             Polaznik/ca
                        </h3>
                    </div>
                    <form onSubmit={submit} className="p-6.5">
                        {/* First Name & Last Name */}
                        <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                            <div className="w-full xl:w-1/2">
                                <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                                    Ime
                                </label>
                                <input
                                    type="text"
                                    name="first_name"
                                    value={data.first_name}
                                    onChange={(e) =>
                                        setData("first_name", e.target.value)
                                    }
                                    placeholder="Unos imena ..."
                                    className="w-full rounded border-[1.5px] border-stroke px-5 py-3"
                                />
                                {errors.first_name && (
                                    <div className="text-red-500 text-sm">
                                        {errors.first_name}
                                    </div>
                                )}
                            </div>
                            <div className="w-full xl:w-1/2">
                                <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                                    Prezime
                                </label>
                                <input
                                    type="text"
                                    name="last_name"
                                    value={data.last_name}
                                    onChange={(e) =>
                                        setData("last_name", e.target.value)
                                    }
                                    placeholder="Unos prezimena ..."
                                    className="w-full rounded border-[1.5px] border-stroke px-5 py-3"
                                />
                                {errors.last_name && (
                                    <div className="text-red-500 text-sm">
                                        {errors.last_name}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Birth Year */}
                        <div className="mb-5">
                            <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                               Godina rođenja
                            </label>
                            <input
                                type="number"
                                name="birth_year"
                                value={data.birth_year}
                                onChange={(e) =>
                                    setData("birth_year", e.target.value)
                                }
                                placeholder="Godina rođenja ..."
                                className="w-full rounded border-[1.5px] border-stroke px-5 py-3"
                            />
                            {errors.birth_year && (
                                <div className="text-red-500 text-sm">
                                    {errors.birth_year}
                                </div>
                            )}
                        </div>

                        {/* Email & Phone */}
                        <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                            <div className="w-full xl:w-1/2">
                                <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData("email", e.target.value)
                                    }
                                    placeholder="johndoe.mail@gmail.com"
                                    className="w-full rounded border-[1.5px] border-stroke px-5 py-3"
                                />
                                {errors.email && (
                                    <div className="text-red-500 text-sm">
                                        {errors.email}
                                    </div>
                                )}
                            </div>
                            <div className="w-full xl:w-1/2">
                                <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                                    Telefon
                                </label>
                                <input
                                    type="text"
                                    name="phone_number"
                                    value={data.phone_number}
                                    onChange={(e) =>
                                        setData("phone_number", e.target.value)
                                    }
                                    placeholder="051 555-011"
                                    className="w-full rounded border-[1.5px] border-stroke px-5 py-3"
                                />
                                {errors.phone_number && (
                                    <div className="text-red-500 text-sm">
                                        {errors.phone_number}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Parent Contact */}
                        <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                            <div className="w-full xl:w-1/2">
                                <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                                    Kontakt roditelja/skrbnika
                                </label>
                                <input
                                    type="text"
                                    name="parent_contact"
                                    value={data.parent_contact}
                                    onChange={(e) =>
                                        setData(
                                            "parent_contact",
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Unos broja telefona/mobitela"
                                    className="w-full rounded border-[1.5px] border-stroke px-5 py-3"
                                />
                            </div>
                            <div className="w-full xl:w-1/2">
                                <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                                     Email roditelja/skrbnika
                                </label>
                                <input
                                    type="email"
                                    name="parent_email"
                                    value={data.parent_email}
                                    onChange={(e) =>
                                        setData("parent_email", e.target.value)
                                    }
                                    placeholder="roditelj@gmail.com"
                                    className="w-full rounded border-[1.5px] border-stroke px-5 py-3"
                                />
                            </div>
                        </div>

                        {/* Active Status */}
                        <div className="mb-5">
                            <label className="mr-4">Aktivan upis?</label>
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) =>
                                    setData("is_active", e.target.checked)
                                }
                            />
                        </div>

                        {/* Submit Button */}
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded bg-primary px-5 py-3 text-white"
                        >
                            {processing ? "Spremam..." : "Spremi upis"}
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
