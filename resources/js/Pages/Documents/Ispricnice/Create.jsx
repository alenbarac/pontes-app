import React, { useState, useRef } from "react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";
import Label from "@/Components/form/Label";
import Input from "@/Components/form/input/InputField";
import toast from "react-hot-toast";
import axios from "axios";
import { EyeIcon } from "@heroicons/react/24/outline";

export default function Create({ template, placeholders, type, isEdit = false }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        type: template?.type || type || "ispricnica",
        name: template?.name || "",
        content: template?.content || "",
        description: template?.description || "",
        is_active: template?.is_active ?? true,
    });

    const textareaRef = useRef(null);

    const handleSubmit = (e) => {
        e.preventDefault();

        const submitMethod = isEdit ? patch : post;
        const routeName = isEdit
            ? route("document-templates.update", template.id)
            : route("document-templates.store");

        submitMethod(routeName, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEdit
                        ? "Predložak uspješno ažuriran."
                        : "Predložak uspješno kreiran."
                );
                router.visit(route("document-templates.ispricnice.index"));
            },
            onError: () => {
                toast.error("Došlo je do pogreške prilikom spremanja.");
            },
        });
    };

    const handlePreview = () => {
        if (!data.content || data.content.trim() === "") {
            toast.error("Molimo unesite sadržaj predloška prije pregleda.");
            return;
        }

        // Encode content and type as query parameters for GET request
        const params = new URLSearchParams({
            content: data.content,
            type: data.type,
        });

        const routeName = template?.id 
            ? route("document-templates.preview.with-id", template.id) + '?' + params.toString()
            : route("document-templates.preview") + '?' + params.toString();

        // Open in new tab using simple link (like invoice slip)
        window.open(routeName, '_blank', 'noopener,noreferrer');
    };

    const insertPlaceholder = (placeholder) => {
        const textarea = textareaRef.current;
        if (textarea) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = data.content;
            const before = text.substring(0, start);
            const after = text.substring(end, text.length);
            const newText = before + `{{${placeholder}}}` + after;
            setData("content", newText);
            setTimeout(() => {
                textarea.focus();
                textarea.setSelectionRange(
                    start + placeholder.length + 4,
                    start + placeholder.length + 4
                );
            }, 0);
        } else {
            setData("content", data.content + `{{${placeholder}}}`);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={isEdit ? "Uredi predložak" : "Novi predložak"} />
            <Breadcrumb
                pageName={
                    isEdit ? "Uredi predložak ispričnice" : "Novi predložak ispričnice"
                }
            />
            <ComponentCard title={isEdit ? "Uredi predložak" : "Novi predložak"}>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <Label htmlFor="name">Naziv predloška *</Label>
                        <Input
                            type="text"
                            id="name"
                            placeholder="Unesite naziv predloška"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                        />
                        {errors.name && (
                            <p className="text-red-500 text-sm mt-1">{errors.name}</p>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="description">Opis</Label>
                        <Input
                            type="text"
                            id="description"
                            placeholder="Unesite opis predloška"
                            value={data.description}
                            onChange={(e) => setData("description", e.target.value)}
                        />
                        {errors.description && (
                            <p className="text-red-500 text-sm mt-1">{errors.description}</p>
                        )}
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <Label htmlFor="content">Sadržaj predloška *</Label>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handlePreview}
                                >
                                    <EyeIcon className="h-4 w-4 mr-2" />
                                    Pregled
                                </Button>
                            </div>
                        </div>
                        <textarea
                            ref={textareaRef}
                            id="content"
                            rows={15}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            placeholder="Unesite sadržaj predloška. Koristite {{placeholder}} za dinamičke vrijednosti."
                            value={data.content}
                            onChange={(e) => setData("content", e.target.value)}
                        />
                        {errors.content && (
                            <p className="text-red-500 text-sm mt-1">{errors.content}</p>
                        )}
                    </div>

                    <div>
                        <Label>Dostupni placeholderi</Label>
                        <div className="mt-2 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div className="grid grid-cols-2 gap-2">
                                {Object.entries(placeholders || {}).map(
                                    ([key, label]) => (
                                        <button
                                            key={key}
                                            type="button"
                                            onClick={() => insertPlaceholder(key)}
                                            className="text-left px-3 py-2 text-sm rounded border border-gray-300 dark:border-gray-700 hover:bg-brand-50 dark:hover:bg-brand-900/20 hover:border-brand-500 transition"
                                        >
                                            <code className="text-brand-600 dark:text-brand-400">
                                                {`{{${key}}}`}
                                            </code>
                                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                {label}
                                            </div>
                                        </button>
                                    )
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="is_active"
                            checked={data.is_active}
                            onChange={(e) => setData("is_active", e.target.checked)}
                            className="rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                        />
                        <Label htmlFor="is_active" className="mb-0">
                            Aktivan
                        </Label>
                    </div>

                    <div className="flex gap-3">
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={processing}
                        >
                            {isEdit ? "Ažuriraj" : "Kreiraj"}
                        </Button>
                        <Link
                            href={route("document-templates.ispricnice.index")}
                            className="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            Odustani
                        </Link>
                    </div>
                </form>

            </ComponentCard>
        </AuthenticatedLayout>
    );
}
