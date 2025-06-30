import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import MemberGroupTable from "@/Components/MemberGroup/MemberGroupTable";

export default function Index({ groups }) {

    console.log(groups);
    
    return (
        <AuthenticatedLayout>
            <Head title="Grupe" />
            <Breadcrumb pageName="Grupe" />
            <ComponentCard
                title="Popis Grupa"
                headerAction={
                    <Link
                        href="/members-groups/create"
                        className="inline-flex items-center justify-center gap-2 rounded-lg transition px-4 py-3 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
                    >
                        Nova grupa
                    </Link>
                }
            >
                 <MemberGroupTable groups={groups} /> 
            </ComponentCard>
        </AuthenticatedLayout>
    );
}
