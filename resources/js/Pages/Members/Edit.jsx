import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import MemberEditForm from "@/Components/Member/MemberEditForm";

export default function Edit({ member, workshops, groups, membershipPlans }) {
    return (
        <AuthenticatedLayout>
            <Head title="Članovi" />
            <Breadcrumb pageName="Članovi" />

            <div className="flex flex-col gap-9">
                <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
                    <div className="border-b border-stroke px-6 py-4 dark:border-strokedark flex justify-between">
                        <h3 className="font-medium text-black dark:text-white">
                            Detalji upisa
                        </h3>
                        <Link
                            href="/members/create"
                            className="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primarydark"
                        >
                            Uredi upis
                        </Link>
                    </div>
                    <div className="p-4">
                        <MemberEditForm member={member} workshops={workshops} groups={groups} membershipPlans={membershipPlans} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
