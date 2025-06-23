import React, { useState } from "react";
import { useForm } from "@inertiajs/react";
import Label from "@/Components/form/Label";
import Select from "@/Components/form/Select";
import Flatpickr from "react-flatpickr";
import { CalendarDaysIcon } from "@heroicons/react/24/outline";
import Button from "@/Components/ui/button/Button";
import toast from "react-hot-toast";

export default function MemberWorkshopAddForm({
    member,
    workshops,
    groupsMap,
    plansMap,
    closeModal,
}) {
    const { data, setData, post, processing, errors } = useForm({
        workshop_id: "",
        group_id: "",
        membership_plan_id: "",
        membership_start_date: new Date().toISOString().slice(0, 10), // today
    });

    const [filteredGroups, setFilteredGroups] = useState([]);
    const [filteredPlans, setFilteredPlans] = useState([]);

    function handleWorkshopSelection(id) {
        setData("workshop_id", id);
        const g = groupsMap[id] || [];
        const p = plansMap[id] || [];
        setFilteredGroups(g);
        setFilteredPlans(p);
        setData("group_id", "");
        setData("membership_plan_id", "");
    }

    function handleSubmit(e) {
        e.preventDefault();
        post(route("members.workshops.store", { member: member.id }), {
            onSuccess: () => {
                closeModal();
                toast.success("Radionica uspješno dodana.");
            },
            onError: () => toast.error("Pokušajte ponovno."),
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4 px-4">
            <div>
                <Label htmlFor="workshop_id">Radionica</Label>
                <Select
                    id="workshop_id"
                    value={data.workshop_id}
                    onChange={handleWorkshopSelection}
                    options={workshops.map((w) => ({
                        value: String(w.id),
                        label: w.name,
                    }))}
                />
                {errors.workshop_id && (
                    <div className="text-red-600">{errors.workshop_id}</div>
                )}
            </div>

            {filteredGroups.length > 0 && (
                <div>
                    <Label htmlFor="group_id">Grupa</Label>
                    <Select
                        id="group_id"
                        value={data.group_id}
                        onChange={(v) => setData("group_id", v)}
                        options={filteredGroups.map((g) => ({
                            value: String(g.id),
                            label: g.name,
                        }))}
                    />
                    {errors.group_id && (
                        <div className="text-red-600">{errors.group_id}</div>
                    )}
                </div>
            )}

            {filteredPlans.length > 0 && (
                <div>
                    <Label htmlFor="membership_plan_id">Plan članarine</Label>
                    <Select
                        id="membership_plan_id"
                        value={data.membership_plan_id}
                        onChange={(v) => setData("membership_plan_id", v)}
                        options={filteredPlans.map((p) => ({
                            value: String(p.id),
                            label: `${p.plan} (${p.total_fee} kn)`,
                        }))}
                    />
                    {errors.membership_plan_id && (
                        <div className="text-red-600">
                            {errors.membership_plan_id}
                        </div>
                    )}
                </div>
            )}

            <div>
                <Label htmlFor="membership_start_date">Datum upisa</Label>
                <div className="relative">
                    <Flatpickr
                        value={data.membership_start_date}
                        onChange={(_, date) =>
                            setData("membership_start_date", date)
                        }
                        options={{ dateFormat: "Y-m-d" }}
                        className="w-full rounded border px-3 py-2"
                    />
                    <span className="absolute right-3 top-1/2 -translate-y-1/2">
                        <CalendarDaysIcon className="h-5 w-5 text-gray-500" />
                    </span>
                </div>
                {errors.membership_start_date && (
                    <div className="text-red-600">
                        {errors.membership_start_date}
                    </div>
                )}
            </div>

            <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={closeModal}>
                    Otkaži
                </Button>
                <Button type="submit" disabled={processing}>
                    Spremi
                </Button>
            </div>
        </form>
    );
}
