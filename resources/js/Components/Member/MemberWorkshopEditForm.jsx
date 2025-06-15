import React, { useEffect, useRef } from "react";
import { useForm } from "@inertiajs/react";
import Label from "@/Components/form/Label";
import Select from "@/Components/form/Select";
import Button from "@/Components/ui/button/Button";
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/themes/light.css";
import { CalendarDaysIcon } from "@heroicons/react/24/outline";
import toast from "react-hot-toast";

const MemberWorkshopEditForm = ({
    member,
    workshops,
    groupsMap,
    plansMap,
    closeModal,
}) => {
    // Extract and stringify initial selections
    const initialWorkshop = member.workshops[0] || {};
    const initialWorkshopId = initialWorkshop.id
        ? String(initialWorkshop.id)
        : "";
    const initialGroupObj = member.workshopGroups[0]?.group || {};
    const initialGroupId = initialGroupObj.id ? String(initialGroupObj.id) : "";
    const initialPlanObj = initialWorkshop.membership_plan || {};
    const initialPlanId = initialPlanObj.id ? String(initialPlanObj.id) : "";
    const initialStartDate = initialPlanObj.start_date || "";

    const { data, setData, patch, processing, errors } = useForm({
        workshop_id: initialWorkshopId,
        group_id: initialGroupId,
        membership_plan_id: initialPlanId,
        start_date: initialStartDate,
    });

    // Keep track of the original workshop to skip initial effect
    const initialWorkshopIdRef = useRef(initialWorkshopId);

    // Reset group and plan only when the user changes workshop after mount
    useEffect(() => {
        if (data.workshop_id !== initialWorkshopIdRef.current) {
            const availableGroups = groupsMap[data.workshop_id] || [];
            setData(
                "group_id",
                availableGroups[0]?.id ? String(availableGroups[0].id) : "",
            );
            const availablePlans = plansMap[data.workshop_id] || [];
            setData(
                "membership_plan_id",
                availablePlans[0]?.id ? String(availablePlans[0].id) : "",
            );
            setData("start_date", availablePlans[0]?.start_date || "");
        }
    }, [data.workshop_id]);

    function handleSubmit(e) {
        e.preventDefault();
        patch(
            route("members.workshops.update", {
            member: member.id,
            workshop: data.workshop_id,
            }),
            {
            onSuccess: () => {       
                closeModal();
                toast.success("Uspješno ažurirano.");
                
            },
            },
        );
    }

    const workshopOptions = workshops.map((w) => ({
        value: String(w.id),
        label: w.name,
    }));
    const groupOptions = (groupsMap[data.workshop_id] || []).map((g) => ({
        value: String(g.id),
        label: g.name,
    }));
    const planOptions = (plansMap[data.workshop_id] || []).map((p) => ({
        value: String(p.id),
        label: `${p.plan} (${p.total_fee} EUR)`,
    }));


    return (
        <form onSubmit={handleSubmit} className="space-y-4 px-2">
            <div>
                <Label htmlFor="workshop_id">Radionica</Label>
                <Select
                    id="workshop_id"
                    value={data.workshop_id}
                    onChange={(value) => setData("workshop_id", value)}
                    options={workshopOptions}
                    error={errors.workshop_id}
                />
            </div>
            <div>
                <Label htmlFor="group_id">Grupa</Label>
                <Select
                    id="group_id"
                    value={data.group_id}
                    onChange={(value) => setData("group_id", value)}
                    options={groupOptions}
                    error={errors.group_id}
                />
            </div>
            <div>
                <Label htmlFor="membership_plan_id">Plan članarine</Label>
                <Select
                    id="membership_plan_id"
                    value={data.membership_plan_id}
                    onChange={(value) => setData("membership_plan_id", value)}
                    options={planOptions}
                    error={errors.membership_plan_id}
                />
            </div>
            <div>
                <Label htmlFor="start_date">Datum upisa</Label>
                <div className="relative">
                    <Flatpickr
                        id="start_date"
                        value={data.start_date}
                        onChange={(selectedDates, dateStr) =>
                            setData("start_date", dateStr)
                        }
                        options={{ dateFormat: "Y-m-d" }}
                        className="w-full py-[10px] pl-3 pr-10 text-sm border border-gray-300 rounded-md focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                    />
                    <span className="absolute text-gray-500 -translate-y-1/2 pointer-events-none right-3 top-1/2 dark:text-gray-400">
                        <CalendarDaysIcon width={18} height={18} />
                    </span>
                </div>
                {errors.start_date && (
                    <div className="text-red-600 text-sm mt-1">
                        {errors.start_date}
                    </div>
                )}
            </div>
            <div className="flex justify-end gap-2 mt-4">
                <Button type="button" variant="secondary" onClick={closeModal}>
                    Otkaži
                </Button>
                <Button type="submit" disabled={processing}>
                    Spremi
                </Button>
            </div>
        </form>
    );
};

export default MemberWorkshopEditForm;
