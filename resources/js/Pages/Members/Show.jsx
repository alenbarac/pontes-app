import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import MemberInfoCard from "@/Components/Member/MemberInfoCard";
import MemberInfoWorkshops from "@/Components/Member/MemberInfoWorkshops";

export default function Show({ member, workshops, groups, membershipPlans, invoicesByWorkshop }) {

    return (
        <AuthenticatedLayout>
            <Head title="Detalji upisa" />
            <Breadcrumb pageName="Detalji upisa" />

            <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 className="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90 lg:mb-7">
                    Profil
                </h3>
                <div className="space-y-6">
                    <MemberInfoCard memberData={member} />
                    
                    <MemberInfoWorkshops
                        memberData={member}
                        workshops={workshops}
                        groups={groups}
                        membershipPlans={membershipPlans}
                        invoicesByWorkshop={invoicesByWorkshop || {}}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
