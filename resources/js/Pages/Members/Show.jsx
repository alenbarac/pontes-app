import React, { useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import MemberInfoCard from "@/Components/Member/MemberInfoCard";
import MemberInfoWorkshops from "@/Components/Member/MemberInfoWorkshops";
import MemberDocuments from "@/Components/Member/MemberDocuments";
import MemberSendHistory from "@/Components/Member/MemberSendHistory";
import { TabButton } from "@/Components/ui/tabs/TabWithUnderline";

function memberGroups(member) {
    const seen = new Set();
    const groups = [];

    for (const entry of member?.workshopGroups || []) {
        const group = entry?.group;
        if (!group?.id || seen.has(group.id)) {
            continue;
        }
        seen.add(group.id);
        groups.push(group);
    }

    return groups;
}

export default function Show({ member, workshops, groups, membershipPlans, invoicesByWorkshop, paymentNotes = [], documents = [] }) {
    const [activeTab, setActiveTab] = useState("radionice");
    const pageUrl = usePage().url || "";
    const preferredGroupId = new URLSearchParams(pageUrl.split("?")[1] || "").get("group");
    const assignedGroups = memberGroups(member);
    const orderedGroups = [...assignedGroups].sort((a, b) => {
        if (String(a.id) === String(preferredGroupId)) return -1;
        if (String(b.id) === String(preferredGroupId)) return 1;
        return 0;
    });
    const memberName = `${member.first_name || ""} ${member.last_name || ""}`.trim() || "Detalji upisa";

    const tabs = [
        { id: "radionice", label: "Radionice" },
        { id: "dokumenti", label: "Dokumenti" },
        { id: "evidencija-slanja", label: "Evidencija slanja" },
        { id: "osnovne-informacije", label: "Osnovne informacije" },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Detalji upisa" />
            <Breadcrumb items={[
                { label: "Članovi", href: route("members.index") },
                ...orderedGroups.map((group) => ({
                    label: group.name,
                    href: route("member-groups.show", group.id),
                })),
                { label: memberName },
            ]} />

            <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between lg:mb-7">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                        Profil
                    </h3>
                    {orderedGroups.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {orderedGroups.map((group) => (
                                <Link
                                    key={group.id}
                                    href={route("member-groups.show", group.id)}
                                    className="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2.5 text-sm text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                                >
                                    {orderedGroups.length > 1
                                        ? `Otvori grupu: ${group.name}`
                                        : "Otvori grupu"}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
                
                <div className="border-b border-gray-200 dark:border-gray-800 mb-6">
                    <nav className="-mb-px flex space-x-2 overflow-x-auto [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 dark:[&::-webkit-scrollbar-thumb]:bg-gray-600 dark:[&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar]:h-1.5">
                        {tabs.map((tab) => (
                            <TabButton
                                key={tab.id}
                                id={tab.id}
                                label={tab.label}
                                isActive={activeTab === tab.id}
                                onClick={() => setActiveTab(tab.id)}
                            />
                        ))}
                    </nav>
                </div>

                <div className="pt-4">
                    {activeTab === "radionice" && (
                        <MemberInfoWorkshops
                            memberData={member}
                            workshops={workshops}
                            groups={groups}
                            membershipPlans={membershipPlans}
                            invoicesByWorkshop={invoicesByWorkshop || {}}
                            paymentNotes={paymentNotes}
                        />
                    )}
                    
                    {activeTab === "dokumenti" && (
                        <MemberDocuments
                            documents={documents}
                            memberId={member.id}
                            member={member}
                        />
                    )}

                    {activeTab === "evidencija-slanja" && (
                        <MemberSendHistory invoicesByWorkshop={invoicesByWorkshop || {}} />
                    )}
                    
                    {activeTab === "osnovne-informacije" && (
                        <MemberInfoCard memberData={member} />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
