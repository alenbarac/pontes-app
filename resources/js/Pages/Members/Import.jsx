import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";

export default function Import() {
    return (
        <AuthenticatedLayout>
            <Head title="Import" />
            <Breadcrumb pageName="Uvoz članova" />
            <ComponentCard
                title="Uvoz članova"
            >
                <h2>Dropzone</h2>
            </ComponentCard>
        </AuthenticatedLayout>
    );
}
