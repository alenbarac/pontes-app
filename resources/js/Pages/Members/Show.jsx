import React, { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import MemberInfoCard from "@/Components/Member/MemberInfoCard";
import MemberInfoWorkshops from "@/Components/Member/MemberInfoWorkshops";
import MemberDocuments from "@/Components/Member/MemberDocuments";
import { TabButton } from "@/Components/ui/tabs/TabWithUnderline";

export default function Show({ member, workshops, groups, membershipPlans, invoicesByWorkshop, documents = [] }) {
    const [activeTab, setActiveTab] = useState("radionice");

    const tabs = [
        { id: "radionice", label: "Radionice" },
        { id: "dokumenti", label: "Dokumenti" },
        { id: "osnovne-informacije", label: "Osnovne informacije" },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Detalji upisa" />
            <Breadcrumb pageName="Detalji upisa" />

            <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 className="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90 lg:mb-7">
                    Profil
                </h3>
                
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
                        />
                    )}
                    
                    {activeTab === "dokumenti" && (
                        <MemberDocuments
                            documents={documents}
                            memberId={member.id}
                            member={member}
                        />
                    )}
                    
                    {activeTab === "osnovne-informacije" && (
                        <MemberInfoCard memberData={member} />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
