import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import InvoiceDataTable from "@/Components/Invoice/InvoiceDataTable";

export default function Index({ 
    invoices, 
    pagination, 
    filter, 
    workshopId, 
    paymentStatus, 
    groupId,
    workshops, 
    paymentStatuses,
    groups,
}) {
    
    return (
        <AuthenticatedLayout>
            <Head title="Članarine" />
            <Breadcrumb pageName="Članarine" />
            <ComponentCard
                title="Članarine"
                headerAction={
                    <Link
                        href="#"
                        className="inline-flex items-center justify-center gap-2 rounded-lg transition px-4 py-3 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
                    >
                        Uvoz uplata
                    </Link>
                }
            >
                <InvoiceDataTable
                    data={invoices.data}
                    pagination={pagination}
                    initialFilter={filter}
                    initialWorkshopId={workshopId}
                    initialPaymentStatus={paymentStatus}
                    initialGroupId={groupId}
                    workshops={workshops}
                    paymentStatuses={paymentStatuses}
                    groups={groups}
                />
            </ComponentCard>
        </AuthenticatedLayout>
    );
}
