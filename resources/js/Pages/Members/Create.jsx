import React from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import MemberCreateForm from "@/Components/Member/MemberCreateForm";
import ComponentCard from "@/Components/common/ComponentCard";

export default function Create({ groups, workshops, membershipPlans }) {
    return (
        <AuthenticatedLayout>
            <Head title="Novi Član" />
            <Breadcrumb pageName="Novi upis" />
            <ComponentCard title="Polaznik/ca">
                <MemberCreateForm
                    groups={groups}
                    workshops={workshops}
                    membershipPlans={membershipPlans}
                />
            </ComponentCard>      
        </AuthenticatedLayout>
    );
}
