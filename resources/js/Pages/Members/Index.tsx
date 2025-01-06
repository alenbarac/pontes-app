import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import DataTable from "@/Components/Tables/DataTable";
import { Member } from "@/types";

interface MembersPageProps {
    members: {
        data: Member[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export default function Index({ members }: MembersPageProps) {
    console.log(members);

    const columns = [
        {
            header: "ID",
            accessorKey: "id",
        },
        {
            header: "Ime",
            accessorKey: "first_name",
        },
        {
            header: "Prezime",
            accessorKey: "last_name",
        },
        {
            header: "Godina rođenja",
            accessorKey: "birth_year",
        },
        {
            header: "Telefon",
            accessorKey: "phone_number",
        },
        {
            header: "Email",
            accessorKey: "email",
        },
        {
            header: "Status",
            accessorKey: "is_active",
            cell: (info: any) => (
                <span
                    className={`inline-block rounded px-2 py-1 text-xs font-medium ${
                        info.getValue()
                            ? "bg-green-100 text-green-800"
                            : "bg-rose-300 text-black dark:bg-rose-500 dark:text-black"
                    }`}
                >
                    {info.getValue() ? "Aktivan" : "Neaktivan"}
                </span>
            ),
        },
    ];

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
                                className="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primarydark">
                                Novi upis
                            </Link>
                        </div>
                    </div>
                    <DataTable
                        data={members.data}
                        columns={columns}
                        pageSizeOptions={[5, 10, 20]}
                        defaultPageSize={10}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
