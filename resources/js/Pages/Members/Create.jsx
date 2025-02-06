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
    console.log("membershipPlans:", membershipPlans);

    const groupedMembershipPlans = workshops.reduce((acc, workshop) => {
        acc[workshop.id] = membershipPlans.filter(
            (plan) => plan.workshop_id === workshop.id,
        );
        return acc;
    }, {});
    return (
        <AuthenticatedLayout>
            <Head title="Novi Član" />

            <Breadcrumb pageName="Novi upis" />

            <div className="flex flex-col gap-9">
                <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
                    <div className="border-b border-stroke px-6 py-4 dark:border-strokedark">
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
                        membershipPlans={groupedMembershipPlans}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
