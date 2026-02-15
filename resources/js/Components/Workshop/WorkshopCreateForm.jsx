import React from "react";
import { router, useForm } from "@inertiajs/react";
import Button from "@/ui/button/Button";
import Label from "../form/Label";
import Input from "../form/input/InputField";
import Select from "../form/Select";
import toast from "react-hot-toast";

const workshopTypes = [
    { value: "Group", label: "Grupna" },
    { value: "Individual", label: "Individualna" },
    { value: "Mixed", label: "Mješovita" },
];

export default function WorkshopCreateForm({ onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        type: "",
        description: "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        post(route("workshops.store"), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Radionica uspješno kreirana.");
                reset();
                if (onClose) {
                    onClose();
                } else {
                    router.visit(route("workshops.index"));
                }
            },
            onError: () => {
                toast.error("Došlo je do pogreške prilikom spremanja.");
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div>
                <Label htmlFor="name">Naziv radionice</Label>
                <Input
                    type="text"
                    id="name"
                    placeholder="Unesite naziv radionice"
                    value={data.name}
                    onChange={(e) => setData("name", e.target.value)}
                />
                {errors.name && (
                    <p className="text-red-500 text-sm">{errors.name}</p>
                )}
            </div>

            <div>
                <Label htmlFor="type">Tip radionice</Label>
                <Select
                    id="type"
                    value={data.type}
                    onChange={(value) => setData("type", value)}
                    options={workshopTypes}
                    placeholder="Odaberite tip radionice"
                />
                {errors.type && (
                    <p className="text-red-500 text-sm">{errors.type}</p>
                )}
            </div>

            <div>
                <Label htmlFor="description">Opis</Label>
                <textarea
                    id="description"
                    rows={4}
                    placeholder="Unesite opis radionice"
                    value={data.description}
                    onChange={(e) => setData("description", e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                />
                {errors.description && (
                    <p className="text-red-500 text-sm">{errors.description}</p>
                )}
            </div>

            <div className="flex justify-end gap-2">
                {onClose && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                    >
                        Odustani
                    </Button>
                )}
                <Button type="submit" variant="primary" disabled={processing}>
                    Spremi
                </Button>
            </div>
        </form>
    );
}
