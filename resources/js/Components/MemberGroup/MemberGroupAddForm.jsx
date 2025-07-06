import React from "react";
import { router, useForm } from "@inertiajs/react";
import Button from "@/ui/button/Button";
import Label from "../form/Label";
import Input from "../form/input/InputField";
import toast from "react-hot-toast";
import Select from "../form/Select";

export default function MemberGroupCreateForm({ onClose, workshops }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        description: "",
        workshop_id: "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        post(route("member-groups.store"), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Grupa uspješno kreirana.");
                reset(); // Clear form
                if (onClose) {
                  onClose();
                } else {
                  router.visit(route("member-groups.index"));
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
                <Label htmlFor="name">Naziv grupe</Label>
                <Input
                    type="text"
                    id="name"
                    placeholder="Unesite naziv grupe"
                    value={data.name}
                    onChange={(e) => setData("name", e.target.value)}
                />
                {errors.name && (
                    <p className="text-red-500 text-sm">{errors.name}</p>
                )}
            </div>

            <div>
                <div>
                    <Label htmlFor="workshop_id">Radionica</Label>
                    <Select
                        id="workshop_id"
                        value={data.workshop_id}
                        onChange={(value) => setData("workshop_id", value)}
                        options={workshops.map((w) => ({
                            value: String(w.id),
                            label: w.name,
                        }))}
                    />
                    {errors.workshop_id && (
                        <div className="text-red-600">{errors.workshop_id}</div>
                    )}
                </div>
            </div>

            <div>
                <Label htmlFor="description">Opis</Label>
                <Input
                    type="text"
                    id="description"
                    placeholder="Unesite opis grupe"
                    value={data.description}
                    onChange={(e) => setData("description", e.target.value)}
                />
                {errors.description && (
                    <p className="text-red-500 text-sm">{errors.description}</p>
                )}
            </div>

            <div className="flex justify-end">
                <Button type="submit" variant="primary" disabled={processing}>
                    Spremi
                </Button>
            </div>
        </form>
    );
}
