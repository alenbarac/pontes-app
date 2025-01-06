import React from "react";
import { Head, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import MemberCreateForm from "@/Components/Member/MemberCreateForm";

export default function Create() {
    return (
        <AuthenticatedLayout>
            <Head title="Novi Član" />

            <Breadcrumb pageName="Novi upis" />

            <div className="flex flex-col gap-9">
                <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
                    <div className="border-b border-stroke px-6.5 py-4 dark:border-strokedark">
                        <h3 className="font-medium text-black dark:text-white">
                             Polaznik/ca
                        </h3>
                    </div>
                    <MemberCreateForm />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
