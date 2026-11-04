import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import toast from "react-hot-toast";
import { Modal } from "@/Components/ui/modal";
import Button from "@/Components/ui/button/Button";
import Label from "@/Components/form/Label";
import Input from "@/Components/form/input/InputField";
import Select from "@/Components/form/Select";
import Flatpickr from "react-flatpickr";
import { CalendarDaysIcon } from "@heroicons/react/24/outline";
import GenderPrompt from "@/Components/Documents/GenderPrompt";
import { saveMemberGenders } from "@/utils/memberGender";
import { notifyMailingStarted } from "@/lib/mailingActivity";

const TYPE_LABELS = {
    ispricnica: "Ispričnice",
    privola: "Privole",
};

function typeLabel(type) {
    return TYPE_LABELS[type] || type;
}

const emptyActivity = {
    name: "",
    start_date: "",
    end_date: "",
    location: "",
    description: "",
};

export default function SendDocumentEmailModal({
    isOpen,
    onClose,
    onSuccess,
    mode = "group",
    groupId = null,
    groupName = "",
    memberId = null,
    memberDocument = null,
    selectedMemberIds = [],
    groupTotalMembers = 0,
}) {
    const [templates, setTemplates] = useState([]);
    const [loadingTemplates, setLoadingTemplates] = useState(false);
    const [documentType, setDocumentType] = useState("");
    const [templateId, setTemplateId] = useState("");
    const [activity, setActivity] = useState(emptyActivity);
    const [preview, setPreview] = useState(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [includeAlreadySent, setIncludeAlreadySent] = useState(false);
    const [genderChoices, setGenderChoices] = useState({});
    const [processing, setProcessing] = useState(false);

    const scope = selectedMemberIds.length > 0 ? "selected" : "all";
    const scopeLabel = mode === "group"
        ? (scope === "all"
            ? `Svi članovi grupe (${groupTotalMembers})`
            : `Odabrani članovi (${selectedMemberIds.length})`)
        : memberDocument?.template_name || "Jedan član";

    const types = useMemo(() => {
        const found = [];
        templates.forEach((template) => {
            if (!found.includes(template.type)) {
                found.push(template.type);
            }
        });
        return found;
    }, [templates]);

    const templatesForType = templates.filter((template) => template.type === documentType);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        setPreview(null);
        setIncludeAlreadySent(false);
        setGenderChoices({});
        setActivity(emptyActivity);
        setDocumentType("");
        setTemplateId("");

        if (mode === "document") {
            return;
        }

        let cancelled = false;
        setLoadingTemplates(true);
        axios.get(route("document-templates.active"))
            .then((response) => {
                if (cancelled) {
                    return;
                }
                const rows = Array.isArray(response.data) ? response.data : [];
                setTemplates(rows);
                const firstType = rows[0]?.type || "";
                setDocumentType(firstType);
                const first = rows.find((row) => row.type === firstType);
                setTemplateId(first ? String(first.id) : "");
            })
            .catch(() => {
                if (!cancelled) {
                    toast.error("Greška pri učitavanju predložaka.");
                    setTemplates([]);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoadingTemplates(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [isOpen, mode]);

    useEffect(() => {
        if (!isOpen || mode !== "document") {
            return;
        }
        loadPreview(false);
        // Preview the existing document as soon as the modal opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isOpen, mode, memberDocument?.id]);

    const endpoints = () => {
        if (mode === "group") {
            return {
                preview: route("member-groups.document-emails.preview", groupId),
                send: route("member-groups.document-emails", groupId),
            };
        }
        if (mode === "document") {
            return {
                preview: route("member-documents.email.preview", memberDocument.id),
                send: route("member-documents.email", memberDocument.id),
            };
        }
        return {
            preview: route("members.document-emails.preview", memberId),
            send: route("members.document-emails", memberId),
        };
    };

    const payload = (resend) => {
        if (mode === "document") {
            return { resend };
        }

        const body = {
            document_template_id: Number(templateId),
            resend,
        };

        if (mode === "group") {
            body.send_scope = scope;
            body.member_ids = selectedMemberIds;
        }

        if (documentType === "privola") {
            body.activity = activity;
        }

        return body;
    };

    const loadPreview = async (resend = includeAlreadySent) => {
        if (mode !== "document" && !templateId) {
            toast.error("Odaberite predložak.");
            return;
        }

        setPreviewLoading(true);
        try {
            const response = await axios.post(endpoints().preview, payload(resend));
            setPreview(response.data);
            setGenderChoices({});
        } catch (error) {
            toast.error(error.response?.data?.message || "Pregled slanja nije uspio.");
            setPreview(null);
        } finally {
            setPreviewLoading(false);
        }
    };

    const saveGenders = async () => {
        const missing = preview?.missing_gender || [];
        if (missing.some((member) => !genderChoices[member.id])) {
            toast.error("Odaberite spol za svakog člana.");
            return;
        }

        setProcessing(true);
        try {
            await saveMemberGenders(genderChoices);
            setProcessing(false);
            await loadPreview(includeAlreadySent);
        } catch (error) {
            toast.error(error.response?.data?.message || "Spol nije spremljen.");
            setProcessing(false);
        }
    };

    const send = async () => {
        setProcessing(true);
        try {
            const response = await axios.post(endpoints().send, payload(includeAlreadySent));
            notifyMailingStarted();
            toast.success(response.data?.message || "Slanje je pokrenuto.");
            setProcessing(false);
            onSuccess?.();
        } catch (error) {
            const missing = error.response?.data?.missing_gender;
            if (Array.isArray(missing) && missing.length > 0) {
                setPreview((current) => ({ ...(current || {}), missing_gender: missing }));
                setProcessing(false);
                return;
            }
            toast.error(error.response?.data?.message || "Slanje nije pokrenuto.");
            setProcessing(false);
        }
    };

    const missingGender = preview?.missing_gender || [];
    const canSend = preview
        && !preview.exceeds_cap
        && !preview.recipient_not_allowed
        && missingGender.length === 0
        && (preview.deliverable_count > 0 || (includeAlreadySent && (preview.already_sent || []).length > 0));

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="max-w-[720px] m-4">
            <div className="no-scrollbar relative w-full max-w-[720px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                <h5 className="mb-1 text-xl font-semibold text-gray-800 dark:text-white/90">
                    Pošalji dokument
                </h5>
                <p className="mb-5 text-sm text-gray-500 dark:text-gray-400">
                    {mode === "group" ? `${groupName || "Grupa"} · ` : ""}
                    {scopeLabel}. Poruka ide na email za račune, s PDF-om u prilogu.
                </p>

                {mode !== "document" && (
                    <div className="mb-5 space-y-4">
                        <div>
                            <Label>Vrsta dokumenta</Label>
                            {loadingTemplates ? (
                                <p className="text-sm text-gray-500">Učitavanje...</p>
                            ) : types.length === 0 ? (
                                <p className="text-sm text-gray-500">Nema aktivnih predložaka.</p>
                            ) : (
                                <Select
                                    value={documentType}
                                    onChange={(value) => {
                                        setDocumentType(value);
                                        const first = templates.find((row) => row.type === value);
                                        setTemplateId(first ? String(first.id) : "");
                                        setPreview(null);
                                    }}
                                    options={types.map((type) => ({ value: type, label: typeLabel(type) }))}
                                />
                            )}
                        </div>
                        {documentType && templatesForType.length > 0 && (
                            <div>
                                <Label>Predložak</Label>
                                <Select
                                    value={templateId}
                                    onChange={(value) => {
                                        setTemplateId(value);
                                        setPreview(null);
                                    }}
                                    options={templatesForType.map((template) => ({
                                        value: String(template.id),
                                        label: template.name,
                                    }))}
                                />
                            </div>
                        )}
                        {documentType === "privola" && templateId && (
                            <div className="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                                <h6 className="font-medium text-gray-800 dark:text-white/90">Podaci o aktivnosti</h6>
                                <div>
                                    <Label htmlFor="send_activity_name">Naziv aktivnosti</Label>
                                    <Input
                                        id="send_activity_name"
                                        value={activity.name}
                                        onChange={(event) => setActivity({ ...activity, name: event.target.value })}
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label>Početni datum</Label>
                                        <div className="relative">
                                            <Flatpickr
                                                value={activity.start_date}
                                                onChange={(_, dateStr) => setActivity({ ...activity, start_date: dateStr })}
                                                options={{ dateFormat: "Y-m-d" }}
                                                className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                            />
                                            <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                                <CalendarDaysIcon className="size-5" />
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <Label>Završni datum</Label>
                                        <div className="relative">
                                            <Flatpickr
                                                value={activity.end_date}
                                                onChange={(_, dateStr) => setActivity({ ...activity, end_date: dateStr })}
                                                options={{ dateFormat: "Y-m-d" }}
                                                className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                            />
                                            <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                                <CalendarDaysIcon className="size-5" />
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="send_activity_location">Lokacija</Label>
                                    <Input
                                        id="send_activity_location"
                                        value={activity.location}
                                        onChange={(event) => setActivity({ ...activity, location: event.target.value })}
                                    />
                                </div>
                            </div>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={previewLoading || !templateId}
                            onClick={() => loadPreview(includeAlreadySent)}
                        >
                            {previewLoading ? "Provjera..." : "Provjeri primatelje"}
                        </Button>
                    </div>
                )}

                {previewLoading && mode === "document" && (
                    <p className="text-sm text-gray-500">Provjera primatelja...</p>
                )}

                {preview && (
                    <div className="space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                            {preview.template_name}
                        </p>
                        {preview.message && (
                            <p className="text-sm text-red-600 dark:text-red-400">{preview.message}</p>
                        )}
                        <ul className="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <li>{preview.deliverable_count} za slanje</li>
                            {(preview.skipped_no_email || []).length > 0 && (
                                <li>
                                    {(preview.skipped_no_email || []).length} bez emaila za račune:{" "}
                                    {preview.skipped_no_email.map((row) => row.name).join(", ")}
                                </li>
                            )}
                            {(preview.already_sent || []).length > 0 && (
                                <li>
                                    {(preview.already_sent || []).length} već poslano:{" "}
                                    {preview.already_sent.map((row) => row.name).join(", ")}
                                </li>
                            )}
                        </ul>

                        {(preview.already_sent || []).length > 0 && (
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={includeAlreadySent}
                                    onChange={(event) => {
                                        const next = event.target.checked;
                                        setIncludeAlreadySent(next);
                                        loadPreview(next);
                                    }}
                                />
                                Pošalji ponovno i onima koji su dokument već primili
                            </label>
                        )}

                        {missingGender.length > 0 && (
                            <div className="space-y-3">
                                <GenderPrompt
                                    members={missingGender}
                                    value={genderChoices}
                                    onChange={setGenderChoices}
                                />
                                <Button type="button" size="sm" disabled={processing} onClick={saveGenders}>
                                    {processing ? "Spremanje..." : "Spremi spol"}
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                <div className="mt-6 flex gap-3">
                    <Button type="button" variant="primary" disabled={processing || !canSend} onClick={send}>
                        {processing ? "Slanje..." : `Pošalji${preview ? ` (${preview.deliverable_count})` : ""}`}
                    </Button>
                    <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                        Odustani
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
