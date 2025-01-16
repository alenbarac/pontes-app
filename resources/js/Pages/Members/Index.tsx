import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import { columns  } from "@/Components/Member/MemberDataTableColumns";
import DataTable from "@/Components/Tables/DataTable";

interface MembersPageProps {
    members: {
        data: any[];
        pagination: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
}

export default function Index({ members }: MembersPageProps) {
    console.log(members);

    return (
        <AuthenticatedLayout>
            <Head title="Članovi" />

            <Breadcrumb pageName="Članovi" />

            <div className="flex flex-col gap-9">
                <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
                    <div className="border-b border-stroke px-6.5 py-4 dark:border-strokedark">
                        <div className="flex justify-between">
                            <h3 className="font-medium text-black dark:text-white">
                                Popis Članova
                            </h3>
                            <Link
                                href="/members/create"
                                className="inline-flex items-center px-3 py-2  text-sm font-medium text-white bg-primary rounded-md hover:bg-primarydark"
                            >
                                Novi upis
                            </Link>
                        </div>
                    </div>
                    <DataTable
                        data={members.data}
                        columns={columns}
                        pagination={members.pagination}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
