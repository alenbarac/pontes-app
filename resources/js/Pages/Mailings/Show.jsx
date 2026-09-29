import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import toast from "react-hot-toast";
import { Modal } from "@/Components/ui/modal";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";
import { Table, TableHeader, TableBody, TableRow, TableCell } from "@/Components/ui/table";
import { notifyMailingStarted } from "@/lib/mailingActivity";

const STATUS_LABEL = {
    queued: "U redu",
    sent: "Poslana",
    failed: "Greška",
    cancelled: "Otkazano",
};

export default function Show({ mailing }) {
    const [retrying, setRetrying] = useState(false);
    const [pending, setPending] = useState(null);
    const [working, setWorking] = useState(false);
    const recipients = mailing.recipients ?? [];

    const retryFailed = () => {
        setRetrying(true);
        router.post(
            route("mailings.retry-failed", mailing.id),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    notifyMailingStarted();
                    const flash = page.props.flash ?? {};
                    if (flash.error) {
                        toast.error(flash.error);
                        return;
                    }
                    if (flash.success) {
                        toast.success(flash.success);
                    }
                },
                onError: () => {
                    toast.error("Ponovno slanje nije pokrenuto.");
                },
                onFinish: () => setRetrying(false),
            },
        );
    };

    const confirmPending = () => {
        if (!pending) {
            return;
        }

        const isDelete = pending === "delete";
        setWorking(true);
        const visit = {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash ?? {};
                if (flash.error) {
                    toast.error(flash.error);
                    return;
                }
                if (flash.success) {
                    toast.success(flash.success);
                }
                setPending(null);
            },
            onError: () => {
                toast.error(isDelete ? "Slanje nije obrisano." : "Slanje nije zatvoreno.");
            },
            onFinish: () => setWorking(false),
        };

        if (isDelete) {
            router.delete(route("mailings.destroy", mailing.id), visit);
            return;
        }

        router.post(route("mailings.close", mailing.id), {}, visit);
    };

    return (
        <AuthenticatedLayout>
            <Head title={mailing.label} />
            <Breadcrumb
                items={[
                    { label: "Evidencija slanja", href: route("mailings.index") },
                    { label: mailing.label },
                ]}
            />
            <ComponentCard
                title={mailing.label}
                headerAction={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={working || mailing.queued === 0 || mailing.is_closed}
                            onClick={() => setPending("close")}
                        >
                            Označi kao završeno
                        </Button>
                        {mailing.failed > 0 && (
                            <Button type="button" size="sm" variant="primary" disabled={retrying} onClick={retryFailed}>
                                {retrying ? "Pokretanje..." : "Ponovi neuspjela"}
                            </Button>
                        )}
                        <Button type="button" size="sm" variant="outline" disabled={working} onClick={() => setPending("delete")}>
                            Obriši
                        </Button>
                    </div>
                }
            >
                <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    {mailing.group_name}
                    {mailing.month_label ? ` · ${mailing.month_label}` : ""}
                </p>

                <dl className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <dt className="text-xs text-gray-500 dark:text-gray-400">Započeto</dt>
                        <dd className="text-sm font-medium text-gray-800 dark:text-white">{mailing.started_at}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-gray-500 dark:text-gray-400">Završeno</dt>
                        <dd className="text-sm font-medium text-gray-800 dark:text-white">
                            {mailing.completed_at ?? "U tijeku"}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-gray-500 dark:text-gray-400">Poslano</dt>
                        <dd className="text-sm font-medium text-gray-800 dark:text-white">
                            {mailing.sent} / {mailing.total}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-gray-500 dark:text-gray-400">Greške</dt>
                        <dd className="text-sm font-medium text-gray-800 dark:text-white">{mailing.failed}</dd>
                    </div>
                </dl>

                <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
                    <div className="max-w-full overflow-x-auto">
                        <Table>
                            <TableHeader className="border-b border-gray-200 bg-gray-50 dark:border-white/[0.05]">
                                <TableRow>
                                    {["Član", "Adresa", "Status", "Greška"].map((heading) => (
                                        <TableCell
                                            key={heading}
                                            isHeader
                                            className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                                        >
                                            {heading}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recipients.map((row) => (
                                    <TableRow key={row.id} className="border-b border-gray-100 dark:border-white/[0.05]">
                                        <TableCell className="px-5 py-4 text-sm">
                                            {row.member_id ? (
                                                <Link
                                                    href={route("members.show", row.member_id)}
                                                    className="font-medium text-brand-600 hover:text-brand-700"
                                                >
                                                    {row.member_name}
                                                </Link>
                                            ) : (
                                                row.member_name
                                            )}
                                        </TableCell>
                                        <TableCell className="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            {row.recipient}
                                        </TableCell>
                                        <TableCell className="px-5 py-4 text-sm text-gray-800 dark:text-white/90">
                                            {STATUS_LABEL[row.status] ?? row.status}
                                        </TableCell>
                                        <TableCell className="px-5 py-4 text-sm text-red-600 dark:text-red-400">
                                            {row.error ?? ""}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </ComponentCard>

            <Modal isOpen={pending !== null} onClose={() => (working ? null : setPending(null))} className="max-w-md m-4">
                <div className="w-full max-w-md rounded-3xl bg-white p-6 dark:bg-gray-900">
                    <h5 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white">
                        {pending === "delete" ? "Obrisati slanje?" : "Označiti kao završeno?"}
                    </h5>
                    <p className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                        {pending === "delete"
                            ? "Zapis i popis primatelja uklanjaju se iz evidencije. Poslane poruke se time ne povlače."
                            : "Poruke koje su još u redu neće se poslati. Već poslane ostaju poslane."}
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" disabled={working} onClick={() => setPending(null)}>
                            Odustani
                        </Button>
                        <Button type="button" variant="primary" size="sm" disabled={working} onClick={confirmPending}>
                            {working ? "Spremanje..." : pending === "delete" ? "Obriši" : "Završi"}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
