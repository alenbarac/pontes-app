import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";

export default function Index({ templates = [] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Email predlošci" />
            <Breadcrumb
                items={[
                    { label: "Email postavke" },
                    { label: "Email predlošci" },
                ]}
            />
            <ComponentCard
                title="Email predlošci"
                desc="Tekst uplatnice i podsjetnika. Iznos, radionica i kontakti i dalje dolaze iz računa."
            >
                <div className="space-y-3">
                    {templates.map((template) => (
                        <Link
                            key={template.key}
                            href={route("email-templates.edit", template.key)}
                            className="block rounded-xl border border-gray-200 px-4 py-4 transition hover:border-brand-300 hover:bg-gray-50 dark:border-gray-800 dark:hover:border-brand-500/40 dark:hover:bg-white/[0.03]"
                        >
                            <div className="text-sm font-medium text-gray-800 dark:text-white/90">{template.name}</div>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{template.description}</p>
                            <p className="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                <span className="text-gray-500 dark:text-gray-400">Predmet: </span>
                                {template.subject}
                            </p>
                        </Link>
                    ))}
                </div>
            </ComponentCard>
        </AuthenticatedLayout>
    );
}
