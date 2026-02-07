import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import { columns } from "@/Components/Member/MemberDataTableColumns";
import MembersDataTable from "@/Components/Member/MembersDataTable";
import ComponentCard from "@/Components/common/ComponentCard";

export default function Index({ 
    members, 
    filter, 
    workshopId, 
    groupId,
    workshops, 
    groups,
}) {
    console.log(members.data);
    return (
        <AuthenticatedLayout>
            <Head title="Članovi" />
            <Breadcrumb pageName="Članovi" />  
                        <ComponentCard
                            title="Popis Članova"
                            headerAction={
                                <Link
                                    href="/members/create"
                                    className="inline-flex items-center justify-center gap-2 rounded-lg transition px-4 py-3 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
                                >
                                    Novi upis
                                </Link>
                            }
                        >              
                            <MembersDataTable
                                data={members.data}
                                columns={columns}
                                pagination={members.pagination}
                                initialFilter={filter}
                                initialWorkshopId={workshopId}
                                initialGroupId={groupId}
                                workshops={workshops}
                                groups={groups}
                            />
                        </ComponentCard>
                   
        </AuthenticatedLayout>
    );
}
