import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import DataTable from "@/Components/Tables/DataTable";
import { Member } from "@/types";

interface MembersPageProps {
    members: {
        data: Member[];
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

   const groupColorMap: { [key: string]: string } = {
       "Juniori 1": "bg-primary/15 text-primary", 
       "Juniori 2": "bg-secondary/15 text-primary", 
       "Srednjoškolci 1": "bg-danger/15 text-dark", 
       "Srednjoškolci 2": "bg-warning/15 text-dark", 
       "Memorabilije 1": "bg-success/15 text-success", 
       "Memorabilije 2": "bg-meta-1/15 text-meta-1",
       "Memorabilije 3": "bg-meta-3/15 text-meta-3",
       "Mini 1": "bg-meta-5/15 text-meta-5",
       "Mini 2": "bg-meta-6/15 text-meta-6",
   };



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
            header: "Kontakt",
            accessorKey: "email",
            cell: (info: any) => {
                const email = info.getValue();
                return email ? (
                    <a
                        href={`mailto:${email}`}
                        className="text-primary hover:underline"
                    >
                        {email}
                    </a>
                ) : (
                    <span className="text-gray-500">Nema email</span>
                );
            },
        },

        {
            header: "Grupe",
            accessorKey: "groups",
            cell: (info: any) => {
                const groups = info.getValue() || [];
                return groups.length > 0 ? (
                    <div className="flex flex-wrap gap-2">
                        {groups.map((group: { id: number; name: string }) => (
                            <span
                                key={group.id}
                                className={`inline-block rounded px-2 py-1 text-xs font-medium ${
                                    groupColorMap[group.name] ||
                                    "bg-gray-200 text-gray-800"
                                }`}
                            >
                                {group.name}
                            </span>
                        ))}
                    </div>
                ) : (
                    <span className="text-gray-500">Nema grupe</span>
                );
            },
        },
        {
            header: "Radionice",
            accessorKey: "workshops",
            cell: (info: any) => {
                const workshops = info.getValue() || []; // Ensure workshops is at least an empty array
                return Array.isArray(workshops) && workshops.length > 0 ? (
                    <ul>
                        {workshops.map(
                            (workshop: { id: number; name: string }) => (
                                <li key={workshop.id} className="text-sm">
                                    {workshop.name}
                                </li>
                            ),
                        )}
                    </ul>
                ) : (
                    <span className="text-gray-500">Nema radionica</span>
                );
            },
        },
    ];

    const memberData = members.data || [];
    const pagination = members.pagination;

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
                        data={memberData}
                        columns={columns}
                        pagination={pagination}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
