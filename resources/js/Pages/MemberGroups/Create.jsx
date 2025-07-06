import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import MemberGroupCreateForm from "@/Components/MemberGroup/MemberGroupAddForm";

export default function Create({workshops}) {

    return (
        <AuthenticatedLayout>
            <Head title="Nova grupa" />
            <Breadcrumb pageName="Nova Grupa" />
            <ComponentCard title="Kreiraj novu grupu">
                <MemberGroupCreateForm workshops={workshops} />
            </ComponentCard>
        </AuthenticatedLayout>
    );
}
