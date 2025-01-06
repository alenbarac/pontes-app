import React from "react";
import { router, useForm } from "@inertiajs/react";
import { MemberFormData } from "@/types";
import toast from "react-hot-toast";

type MemberFormProps = {
    onSubmit?: (data: MemberFormData) => void;
    initialData?: Partial<MemberFormData>;
    submitButtonText?: string;
};

const MemberCreateForm: React.FC<MemberFormProps> = ({
    onSubmit,
    initialData,
    submitButtonText = "Spremi upis",
}) => {
    const { data, setData, post, processing, errors } = useForm<MemberFormData>(
        {
            first_name: initialData?.first_name || "",
            last_name: initialData?.last_name || "",
            birth_year: initialData?.birth_year || "",
            phone_number: initialData?.phone_number || "",
            email: initialData?.email || "",
            is_active: initialData?.is_active ?? true,
            parent_contact: initialData?.parent_contact || "",
            parent_email: initialData?.parent_email || "",
        },
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(route("members.store"), {
            onSuccess: () => {
                toast.success("Član je uspješno dodan!");
                router.visit(route("members.index"));
            },
            onError: (errors) => {
                if (errors.email) {
                    toast.error(errors.email);
                } else {
                    toast.error("Došlo je do greške. Pokušajte ponovno.");
                }
            },
        });
    };


    return (
        <form
            onSubmit={handleSubmit}
            className="p-6.5 bg-white rounded-md shadow-md dark:bg-boxdark"
        >
            {/* First Name & Last Name */}
            <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                <div className="w-full xl:w-1/2">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Ime
                    </label>
                    <input
                        type="text"
                        value={data.first_name}
                        onChange={(e) => setData("first_name", e.target.value)}
                        placeholder="Unos imena ..."
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
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
                        value={data.last_name}
                        onChange={(e) => setData("last_name", e.target.value)}
                        placeholder="Unos prezimena ..."
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                    />
                    {errors.last_name && (
                        <div className="text-red-500 text-sm">
                            {errors.last_name}
                        </div>
                    )}
                </div>
            </div>

            {/* Birth Year, Email, and Phone in One Row */}
            <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                <div className="w-full xl:w-1/3">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Godina rođenja
                    </label>
                    <input
                        type="number"
                        value={data.birth_year}
                        onChange={(e) => setData("birth_year", e.target.value)}
                        placeholder="Godina rođenja ..."
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                    />
                    {errors.birth_year && (
                        <div className="text-red-500 text-sm">
                            {errors.birth_year}
                        </div>
                    )}
                </div>
                <div className="w-full xl:w-1/3">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Email
                    </label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                        placeholder="johndoe.mail@gmail.com"
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                    />
                    {errors.email && (
                        <div className="text-red-500 text-sm">
                            {errors.email}
                        </div>
                    )}
                </div>
                <div className="w-full xl:w-1/3">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Telefon
                    </label>
                    <input
                        type="text"
                        value={data.phone_number}
                        onChange={(e) =>
                            setData("phone_number", e.target.value)
                        }
                        placeholder="051 555-011"
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                    />
                    {errors.phone_number && (
                        <div className="text-red-500 text-sm">
                            {errors.phone_number}
                        </div>
                    )}
                </div>
            </div>

            {/* Parent Contact & Email */}
            <div className="mb-5 flex flex-col gap-6 xl:flex-row">
                <div className="w-full xl:w-1/2">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Kontakt roditelja/skrbnika
                    </label>
                    <input
                        type="text"
                        value={data.parent_contact}
                        onChange={(e) =>
                            setData("parent_contact", e.target.value)
                        }
                        placeholder="Kontakt roditelja"
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                    />
                </div>
                <div className="w-full xl:w-1/2">
                    <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                        Email roditelja/skrbnika
                    </label>
                    <input
                        type="email"
                        value={data.parent_email}
                        onChange={(e) =>
                            setData("parent_email", e.target.value)
                        }
                        placeholder="roditelj@gmail.com"
                        className="w-full rounded border border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none focus:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                    />
                </div>
            </div>

            {/* Active Status */}
            <div className="mb-5">
                <label className="mr-2 text-sm font-medium text-black dark:text-white">
                    Aktivan upis?
                </label>
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData("is_active", e.target.checked)}
                />
            </div>

            {/* Submit */}
            <button
                type="submit"
                disabled={processing}
                className="w-full rounded bg-primary px-5 py-3 text-white"
            >
                {processing ? "Spremam..." : submitButtonText}
            </button>
        </form>
    );
};

export default MemberCreateForm;
