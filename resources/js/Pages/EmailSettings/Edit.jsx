import { useState } from "react";
import { Head, useForm } from "@inertiajs/react";
import toast from "react-hot-toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";
import { Modal } from "@/Components/ui/modal";
import MailSendingNotice from "@/Components/Mail/MailSendingNotice";

export default function Edit({ setting, allowlist = [] }) {
    const testing = Boolean(setting.test_sending);
    const canEnableTest = allowlist.length > 0;
    const form = useForm({
        test_sending: testing,
        confirm_live: false,
    });
    const [confirmLive, setConfirmLive] = useState(false);

    const save = (testSending, confirmLiveSend = false) => {
        form.transform(() => ({
            test_sending: testSending,
            confirm_live: confirmLiveSend,
        }));
        form.patch(route("email-settings.update"), {
            preserveScroll: true,
            onSuccess: (page) => {
                const message = page.props.flash?.success;
                if (message) {
                    toast.success(message);
                }
                setConfirmLive(false);
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Slanje" />
            <Breadcrumb
                items={[
                    { label: "Email postavke" },
                    { label: "Slanje" },
                ]}
            />
            <ComponentCard
                title="Slanje"
                desc="Testno slanje koristi adrese iz MAIL_RECIPIENT_ALLOWLIST. Isključeno slanje ide na e-mail za račune."
            >
                <MailSendingNotice always />

                <div>
                    <p className="text-sm font-medium text-gray-800 dark:text-white/90">Dopuštene adrese</p>
                    {allowlist.length === 0 ? (
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Popis je prazan. Testno slanje se ne može uključiti dok MAIL_RECIPIENT_ALLOWLIST nema barem jednu adresu.
                        </p>
                    ) : (
                        <ul className="mt-2 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                            {allowlist.map((address) => (
                                <li key={address}>{address}</li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="flex flex-wrap justify-end gap-2">
                    {testing ? (
                        <Button type="button" size="sm" disabled={form.processing} onClick={() => setConfirmLive(true)}>
                            Isključi testno slanje
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={form.processing || !canEnableTest}
                            onClick={() => save(true)}
                        >
                            Uključi testno slanje
                        </Button>
                    )}
                </div>
                {form.errors.test_sending && (
                    <p className="text-sm text-error-500">{form.errors.test_sending}</p>
                )}
                {form.errors.confirm_live && (
                    <p className="text-sm text-error-500">{form.errors.confirm_live}</p>
                )}
            </ComponentCard>

            <Modal isOpen={confirmLive} onClose={() => (form.processing ? null : setConfirmLive(false))} className="m-4 max-w-md">
                <div className="w-full max-w-md rounded-3xl bg-white p-6 dark:bg-gray-900">
                    <h5 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white">Isključiti testno slanje?</h5>
                    <p className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                        Poruke će ići na e-mail za račune, uključujući one koje su već u redu. Popis dopuštenih adresa se više ne primjenjuje.
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" disabled={form.processing} onClick={() => setConfirmLive(false)}>
                            Odustani
                        </Button>
                        <Button type="button" size="sm" disabled={form.processing} onClick={() => save(false, true)}>
                            {form.processing ? "Spremanje..." : "Uključi stvarno slanje"}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
