/* resources/js/Components/Member/MemberWorkshopEditForm.jsx */
import React from "react";
import { useForm } from "@inertiajs/react";
import Label from "@/Components/form/Label";
import Select from "@/Components/form/Select";
import Button from "@/Components/ui/button/Button";
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/themes/light.css";
import { CalendarDaysIcon } from "@heroicons/react/24/outline";
import toast from "react-hot-toast";
import Input from "../form/input/InputField";

const MemberWorkshopEditForm = ({
    member,
    workshop,
    groups = [],
    plans = [],
    closeModal,
}) => {
    
    const assignedGroupRecord = member.workshopGroups.find(
        (g) => String(g.workshop_id) === String(workshop.id),
    );

    const initGroupId = assignedGroupRecord
        ? String(assignedGroupRecord.group.id)
        : "";

  // Determine the initially assigned plan
  const initPlan = workshop.membership_plan || {};
  const initPlanId = initPlan.id ? String(initPlan.id) : "";

  // Determine the initially assigned start date
  const initStartDate = initPlan.start_date || "";

  const { data, setData, patch, processing, errors } = useForm({
    workshop_id: String(workshop.id),
    group_id: initGroupId,
    membership_plan_id: initPlanId,
    start_date: initStartDate,
  });

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

    const groupOptions = groups.map((g) => ({
        value: String(g.id),
        label: g.name,
    }));
    const planOptions = plans.map((p) => ({
        value: String(p.id),
        label: `${p.plan} (${p.total_fee} EUR)`,
    }));

    return (
        <form onSubmit={handleSubmit} className="space-y-4 px-2">
            {/* Hidden workshop_id - cannot be changed */}
            <input type="hidden" name="workshop_id" value={data.workshop_id} />

           {/*  <div>
                <Label>Radionica</Label>
                <div className="text-sm font-medium text-gray-800 dark:text-white/90">
                    {workshop.name}
                </div>
            </div> */}

            <div className="mt-5">
                <Label htmlFor="first_name">Radionica</Label>
                <Input
                    type="text"
                    id="workshop_id"
                    disabled
                    value={workshop.name}
                    readOnly
                    className="text-dark cursor-not-allowed"
                />
                {errors.first_name && (
                    <p className="text-red-500 text-sm">{errors.first_name}</p>
                )}
            </div>

            <div>
                <Label htmlFor="group_id">Grupa</Label>
                <Select
                    id="group_id"
                    value={data.group_id}
                    onChange={(value) => setData("group_id", value)}
                    options={groupOptions}
                />
                {errors.group_id && (
                    <div className="text-red-600 text-sm mt-1">
                        {errors.group_id}
                    </div>
                )}
            </div>

            <div>
                <Label htmlFor="membership_plan_id">Plan članarine</Label>
                <Select
                    id="membership_plan_id"
                    value={data.membership_plan_id}
                    onChange={(value) => setData("membership_plan_id", value)}
                    options={planOptions}
                />
                {errors.membership_plan_id && (
                    <div className="text-red-600 text-sm mt-1">
                        {errors.membership_plan_id}
                    </div>
                )}
            </div>

            <div>
                <Label htmlFor="start_date">Datum upisa</Label>
                <div className="relative">
                    <Flatpickr
                        id="start_date"
                        value={data.start_date}
                        onChange={(_, dateStr) =>
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
