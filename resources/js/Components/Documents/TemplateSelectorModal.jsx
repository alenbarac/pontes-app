import React, { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import { Modal } from "@/Components/ui/modal";
import Button from "@/Components/ui/button/Button";
import Label from "@/Components/form/Label";
import Input from "@/Components/form/input/InputField";
import Select from "@/Components/form/Select";
import toast from "react-hot-toast";
import axios from "axios";

export default function TemplateSelectorModal({
    isOpen,
    onClose,
    selectedMembers,
    members,
    templateType: initialTemplateType = null,
    onSuccess,
}) {
    const [documentType, setDocumentType] = useState(initialTemplateType || "");
    const [templates, setTemplates] = useState([]);
    const [selectedTemplateId, setSelectedTemplateId] = useState("");
    const [loading, setLoading] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [activityData, setActivityData] = useState({
        name: "",
        start_date: "",
        end_date: "",
        location: "",
        description: "",
    });

    useEffect(() => {
        if (isOpen) {
            // Reset state when modal opens
            setDocumentType(initialTemplateType || "");
            setSelectedTemplateId("");
            setTemplates([]);
            setActivityData({
                name: "",
                start_date: "",
                end_date: "",
                location: "",
                description: "",
            });
        }
    }, [isOpen, initialTemplateType]);

    useEffect(() => {
        if (isOpen && documentType) {
            loadTemplates();
        }
    }, [isOpen, documentType]);

    const loadTemplates = async () => {
        if (!documentType) return;
        
        setLoading(true);
        try {
            const response = await axios.get(route("document-templates.active"), {
                params: { type: documentType },
            });
            
            if (response.data && Array.isArray(response.data)) {
                setTemplates(response.data);
                if (response.data.length > 0) {
                    setSelectedTemplateId(String(response.data[0].id));
                } else {
                    setSelectedTemplateId("");
                }
            } else {
                setTemplates([]);
                setSelectedTemplateId("");
            }
        } catch (error) {
            console.error("Error loading templates:", error);
            toast.error("Greška pri učitavanju predložaka: " + (error.response?.data?.message || error.message));
            setTemplates([]);
            setSelectedTemplateId("");
        } finally {
            setLoading(false);
        }
    };

    const selectedMembersData = members.filter((m) =>
        selectedMembers.includes(m.id)
    );

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!selectedTemplateId) {
            toast.error("Molimo odaberite predložak.");
            return;
        }

        if (selectedMembers.length === 0) {
            toast.error("Molimo odaberite barem jednog člana.");
            return;
        }

        setProcessing(true);

        const additionalData = {};
        if (documentType === "privola") {
            additionalData.activity = activityData;
        }

        const isBulk = selectedMembers.length > 1;
        const routeName = isBulk
            ? route("document-templates.generate-bulk", selectedTemplateId)
            : route("document-templates.generate", selectedTemplateId);

        const data = {
            member_ids: selectedMembers,
            ...additionalData,
        };

        if (!isBulk) {
            data.member_id = selectedMembers[0];
        }

        // Get template name for filename
        const selectedTemplate = templates.find(t => String(t.id) === selectedTemplateId);
        const templateName = selectedTemplate?.name || 'documents';

        try {
            // For both single and bulk, just save to member profiles
            await axios.post(routeName, data);

            if (isBulk) {
                toast.success(
                    `Generirani dokumenti za ${selectedMembers.length} članova.`
                );
            } else {
                toast.success("Dokument generiran.");
            }

            setProcessing(false);
            onSuccess();
        } catch (error) {
            console.error('Error generating document:', error);
            toast.error(
                error.response?.data?.message ||
                "Došlo je do pogreške prilikom generiranja dokumenta."
            );
            setProcessing(false);
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            className="max-w-[700px] m-4"
        >
            <div className="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                    Generiraj dokumente
                </h5>

                <div className="mb-6">
                    <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Odabrano je <strong>{selectedMembers.length}</strong>{" "}
                        članova:
                    </p>
                    <div className="max-h-32 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-800">
                        <ul className="space-y-1">
                            {selectedMembersData.map((member) => (
                                <li
                                    key={member.id}
                                    className="text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {member.first_name} {member.last_name}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="document_type">Vrsta dokumenta *</Label>
                        <Select
                            id="document_type"
                            value={documentType}
                            onChange={(value) => {
                                setDocumentType(value);
                                setSelectedTemplateId("");
                                setTemplates([]);
                            }}
                            options={[
                                { value: "ispricnica", label: "Ispričnice" },
                                { value: "privola", label: "Privole" },
                            ]}
                        />
                    </div>

                    {documentType && (
                        <div>
                            <Label htmlFor="template">Predložak *</Label>
                            {loading ? (
                                <p className="text-sm text-gray-500">Učitavanje...</p>
                            ) : templates.length === 0 ? (
                                <p className="text-sm text-gray-500">
                                    Nema dostupnih predložaka za ovu vrstu dokumenta.
                                </p>
                            ) : (
                                <Select
                                    id="template"
                                    value={selectedTemplateId}
                                    onChange={(value) => setSelectedTemplateId(value)}
                                    options={templates.map((t) => ({
                                        value: String(t.id),
                                        label: t.name,
                                    }))}
                                />
                            )}
                        </div>
                    )}

                    {documentType === "privola" && selectedTemplateId && (
                        <div className="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <h6 className="font-medium text-gray-800 dark:text-white/90">
                                Podaci o aktivnosti
                            </h6>
                            <div>
                                <Label htmlFor="activity_name">Naziv aktivnosti</Label>
                                <Input
                                    type="text"
                                    id="activity_name"
                                    value={activityData.name}
                                    onChange={(e) =>
                                        setActivityData({
                                            ...activityData,
                                            name: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="activity_start_date">
                                        Početni datum
                                    </Label>
                                    <Input
                                        type="date"
                                        id="activity_start_date"
                                        value={activityData.start_date}
                                        onChange={(e) =>
                                            setActivityData({
                                                ...activityData,
                                                start_date: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="activity_end_date">
                                        Završni datum
                                    </Label>
                                    <Input
                                        type="date"
                                        id="activity_end_date"
                                        value={activityData.end_date}
                                        onChange={(e) =>
                                            setActivityData({
                                                ...activityData,
                                                end_date: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            </div>
                            <div>
                                <Label htmlFor="activity_location">Lokacija</Label>
                                <Input
                                    type="text"
                                    id="activity_location"
                                    value={activityData.location}
                                    onChange={(e) =>
                                        setActivityData({
                                            ...activityData,
                                            location: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div>
                                <Label htmlFor="activity_description">Opis</Label>
                                <textarea
                                    id="activity_description"
                                    rows={3}
                                    className="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    value={activityData.description}
                                    onChange={(e) =>
                                        setActivityData({
                                            ...activityData,
                                            description: e.target.value,
                                        })
                                    }
                                />
                            </div>
                        </div>
                    )}

                    <div className="flex gap-3 pt-4">
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={processing || loading || !documentType || !selectedTemplateId || templates.length === 0}
                        >
                            {processing
                                ? "Generiranje..."
                                : selectedMembers.length > 1
                                  ? "Generiraj dokumente (ZIP)"
                                  : "Generiraj dokument"}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Odustani
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
