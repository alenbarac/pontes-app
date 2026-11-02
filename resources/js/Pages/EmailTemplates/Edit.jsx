import { useEffect, useState } from "react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import toast from "react-hot-toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";
import Label from "@/Components/form/Label";
import Input from "@/Components/form/input/InputField";
import TextArea from "@/Components/form/input/TextArea";
import { Modal } from "@/Components/ui/modal";
import { fillEmailTemplate } from "@/utils/emailTemplate";

export default function Edit({ template, placeholders = [], sample }) {
    const form = useForm({
        subject: template.subject,
        heading: template.heading,
        greeting: template.greeting,
        intro: template.intro,
        sign_off: template.sign_off,
    });
    const [confirmReset, setConfirmReset] = useState(false);
    const [resetting, setResetting] = useState(false);

    useEffect(() => {
        form.setData({
            subject: template.subject,
            heading: template.heading,
            greeting: template.greeting,
            intro: template.intro,
            sign_off: template.sign_off,
        });
        form.clearErrors();
        // Reload the fields only after a save or reset, not on every keystroke.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [template.updated_at]);

    const save = (event) => {
        event.preventDefault();
        form.patch(route("email-templates.update", template.key), {
            preserveScroll: true,
            onSuccess: (page) => {
                const message = page.props.flash?.success;
                if (message) {
                    toast.success(message);
                }
            },
        });
    };

    const resetTemplate = () => {
        setResetting(true);
        router.post(
            route("email-templates.reset", template.key),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const message = page.props.flash?.success;
                    if (message) {
                        toast.success(message);
                    }
                    setConfirmReset(false);
                },
                onFinish: () => setResetting(false),
            },
        );
    };

    const values = {
        ime: sample?.ime || "",
        opis: sample?.opis || "",
        studio: sample?.studio || "",
    };
    const preview = {
        heading: fillEmailTemplate(form.data.heading, values),
        greeting: fillEmailTemplate(form.data.greeting, values),
        intro: fillEmailTemplate(form.data.intro, values),
        signOff: fillEmailTemplate(form.data.sign_off, values),
        subject: fillEmailTemplate(form.data.subject, values),
    };

    return (
        <AuthenticatedLayout>
            <Head title={template.name} />
            <Breadcrumb
                items={[
                    { label: "Email predlošci", href: route("email-templates.index") },
                    { label: template.name },
                ]}
            />
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <ComponentCard
                    title={template.name}
                    desc={template.description}
                    headerAction={
                        <Link
                            href={route("email-templates.index")}
                            className="text-sm text-gray-500 hover:text-brand-600 dark:text-gray-400"
                        >
                            Svi predlošci
                        </Link>
                    }
                >
                    <form onSubmit={save} className="space-y-5">
                        <Field label="Predmet" htmlFor="subject" error={form.errors.subject}>
                            <Input
                                id="subject"
                                value={form.data.subject}
                                error={Boolean(form.errors.subject)}
                                onChange={(event) => form.setData("subject", event.target.value)}
                            />
                        </Field>
                        <Field label="Naslov" htmlFor="heading" error={form.errors.heading}>
                            <Input
                                id="heading"
                                value={form.data.heading}
                                error={Boolean(form.errors.heading)}
                                onChange={(event) => form.setData("heading", event.target.value)}
                            />
                        </Field>
                        <Field label="Pozdrav" htmlFor="greeting" error={form.errors.greeting}>
                            <Input
                                id="greeting"
                                value={form.data.greeting}
                                error={Boolean(form.errors.greeting)}
                                onChange={(event) => form.setData("greeting", event.target.value)}
                            />
                        </Field>
                        <Field label="Uvod" htmlFor="intro" error={form.errors.intro}>
                            <TextArea
                                id="intro"
                                rows={5}
                                placeholder="Tekst uvoda"
                                value={form.data.intro}
                                error={Boolean(form.errors.intro)}
                                onChange={(value) => form.setData("intro", value)}
                            />
                        </Field>
                        <Field
                            label="Završetak"
                            htmlFor="sign_off"
                            error={form.errors.sign_off}
                            hint="Ispod ovoga automatski ide naziv studija i kontakt."
                        >
                            <Input
                                id="sign_off"
                                value={form.data.sign_off}
                                error={Boolean(form.errors.sign_off)}
                                onChange={(event) => form.setData("sign_off", event.target.value)}
                            />
                        </Field>

                        <div className="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-400">
                            <p className="font-medium text-gray-700 dark:text-gray-300">Oznake u tekstu</p>
                            <ul className="mt-2 space-y-1">
                                {placeholders.map((item) => (
                                    <li key={item.token}>
                                        <span className="font-medium text-gray-800 dark:text-white/90">{item.token}</span>
                                        {" — "}
                                        {item.label}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setConfirmReset(true)}>
                                Vrati izvorni tekst
                            </Button>
                            <Button type="submit" size="sm" disabled={form.processing}>
                                {form.processing ? "Spremanje..." : "Spremi"}
                            </Button>
                        </div>
                    </form>
                </ComponentCard>

                <ComponentCard title="Pregled" desc="Primjer s izmišljenim članom. Podaci o računu i kontakti nisu dio predloška.">
                    <div className="rounded-xl bg-gray-100 p-4 dark:bg-gray-900">
                        <p className="mb-3 text-xs text-gray-500 dark:text-gray-400">Predmet: {preview.subject}</p>
                        <div className="rounded-lg bg-white px-5 py-5 text-sm text-gray-800 dark:bg-gray-800 dark:text-white/90">
                            <p className="text-base font-semibold">{preview.heading}</p>
                            <p className="mt-4 whitespace-pre-line">{preview.greeting}</p>
                            <p className="mt-3 whitespace-pre-line">{preview.intro}</p>
                            <div className="mt-4 rounded-md border-l-4 border-brand-500 bg-gray-50 px-4 py-3 dark:bg-white/[0.03]">
                                <p className="font-medium">{sample?.opis}</p>
                                <dl className="mt-2 space-y-1">
                                    {(sample?.facts || []).map((fact) => (
                                        <div key={fact.label} className="flex gap-3">
                                            <dt className="font-medium">{fact.label}:</dt>
                                            <dd>{fact.value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                            <p className="mt-4 whitespace-pre-line border-t border-gray-200 pt-4 text-gray-600 dark:border-gray-700 dark:text-gray-300">
                                {preview.signOff}
                            </p>
                            {(sample?.studio || (sample?.contacts || []).length > 0) && (
                                <p className="mt-4 text-gray-600 dark:text-gray-300">
                                    {sample?.studio || null}
                                    {(sample?.contacts || []).map((contact, index) => (
                                        <span key={contact}>
                                            {sample?.studio || index > 0 ? <br /> : null}
                                            {contact}
                                        </span>
                                    ))}
                                </p>
                            )}
                        </div>
                    </div>
                </ComponentCard>
            </div>

            <Modal isOpen={confirmReset} onClose={() => (resetting ? null : setConfirmReset(false))} className="m-4 max-w-md">
                <div className="w-full max-w-md rounded-3xl bg-white p-6 dark:bg-gray-900">
                    <h5 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white">Vratiti izvorni tekst?</h5>
                    <p className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                        {template.name} vraća se na tekst koji je došao s aplikacijom. Već poslane poruke se ne mijenjaju.
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" disabled={resetting} onClick={() => setConfirmReset(false)}>
                            Odustani
                        </Button>
                        <Button type="button" size="sm" disabled={resetting} onClick={resetTemplate}>
                            {resetting ? "Vraćanje..." : "Vrati"}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function Field({ label, htmlFor, error, hint, children }) {
    return (
        <div>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && !error && <p className="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{hint}</p>}
            {error && <p className="mt-1.5 text-xs text-error-500">{error}</p>}
        </div>
    );
}
