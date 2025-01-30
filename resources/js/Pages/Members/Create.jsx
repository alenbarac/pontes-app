import React from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import MemberCreateForm from "@/Components/Member/MemberCreateForm";

export default function Create({
    groups = [],
    workshops = [],
    membershipPlans = [],
}) {
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
                    <MemberCreateForm
                        groups={groups.map((group) => ({
                            id: group.id,
                            text: group.name ?? "",
                        }))}
                        workshops={workshops.map((workshop) => ({
                            id: workshop.id,
                            text: workshop.name ?? "",
                        }))}
                        membershipPlans={membershipPlans.map((plan) => ({
                            id: plan.id,
                            plan: plan.plan ?? "",
                            total_fee: plan.total_fee ?? 0,
                        }))}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
