import React from "react";
import { router, useForm } from "@inertiajs/react";

import toast from "react-hot-toast";
import MultiSelect from "../MultiSelect";



const MemberCreateForm = ({
    onSubmit,
    initialData = {}, // Default empty object
    groups,
    workshops,
    membershipPlans,
    submitButtonText = "Spremi upis",
}) => {
    const { data, setData, post, processing, errors } = useForm({
        first_name: initialData?.first_name || "",
        last_name: initialData?.last_name || "",
        birth_year: initialData?.birth_year || "",
        phone_number: initialData?.phone_number || "",
        email: initialData?.email || "",
        is_active: initialData?.is_active ?? true,
        parent_contact: initialData?.parent_contact || "",
        parent_email: initialData?.parent_email || "",
        group_ids: initialData?.group_ids || [],
        workshop_ids: initialData?.workshop_ids || [],
        workshopsWithMemberships: initialData?.workshopsWithMemberships || [],
    });

    const handleWorkshopSelection = (selectedWorkshops) => {
        setData("workshop_ids", selectedWorkshops);

        const updatedWorkshopsWithMemberships = selectedWorkshops.map((id) => {
            const existing = data.workshopsWithMemberships?.find(
                (item) => item.workshop_id === id
            );
            return existing || { workshop_id: id, membership_plan: "" };
        });

        setData("workshopsWithMemberships", updatedWorkshopsWithMemberships);
    };

    const handleMembershipPlanSelection = (
        workshopId,
        selectedPlan
    ) => {
        const updated = data.workshopsWithMemberships?.map((item) =>
            item.workshop_id === workshopId
                ? { ...item, membership_plan: selectedPlan }
                : item
        );

        setData("workshopsWithMemberships", updated);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        // Use custom onSubmit if provided, otherwise use Inertia post
        if (onSubmit) {
            onSubmit(data);
        } else {
            post(route("members.store"), {
                onSuccess: () => {
                    toast.success("Član je uspješno dodan!");
                    router.visit(route("members.index"));
                },
                onError: (errors) => {
                    if (errors.email) {
                        toast.error(errors.email);
                    } else {
                        toast.error("Došlo je do greške. Pokušajte ponovno.");
                    }
                },
            });
        }
    };

   return (
       <form
           onSubmit={handleSubmit}
           className="rounded-md bg-white shadow-md dark:bg-boxdark"
       >
           <div className="px-6">
               {/* Row 1: Workshop & Group Selection */}
               <div className="mb-5 flex flex-col gap-6 md:flex-row">
                   <div className="w-full xl:w-1/2">
                       <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                           Radionica/e
                       </label>
                       <MultiSelect
                           options={workshops}
                           value={data.workshop_ids || []}
                           onChange={handleWorkshopSelection}
                           placeholder="Odaberi radionice..."
                       />
                       {errors.workshop_ids && (
                           <div className="text-red-500 text-sm">
                               {errors.workshop_ids}
                           </div>
                       )}
                   </div>

                   <div className="w-full xl:w-1/2">
                       <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                           Grupe
                       </label>
                       <MultiSelect
                           options={groups}
                           value={data.group_ids || []}
                           onChange={(value) => setData("group_ids", value)}
                           placeholder="Odaberi grupe..."
                       />
                       {errors.group_ids && (
                           <div className="text-red-500 text-sm">
                               {errors.group_ids}
                           </div>
                       )}
                   </div>
               </div>

               {/* Row 2: Membership Plans */}
               {data.workshopsWithMemberships.map((item) => {
                   const workshop = workshops.find(
                       (w) => w.id === item.workshop_id,
                   );
                   return (
                       <div key={item.workshop_id} className="mb-5">
                           <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                               Plan za radionicu: {workshop?.text}
                           </label>
                           <div className="flex flex-col gap-3">
                               {membershipPlans.map((plan) => (
                                   <div
                                       key={plan.id}
                                       className="flex items-center space-x-2"
                                   >
                                       <input
                                           type="radio"
                                           id={`workshop-${item.workshop_id}-plan-${plan.id}`}
                                           name={`workshop-${item.workshop_id}`}
                                           value={plan.plan}
                                           checked={
                                               item.membership_plan ===
                                               plan.plan
                                           }
                                           onChange={() =>
                                               handleMembershipPlanSelection(
                                                   item.workshop_id,
                                                   plan.plan,
                                               )
                                           }
                                       />
                                       <label
                                           htmlFor={`workshop-${item.workshop_id}-plan-${plan.id}`}
                                           className="text-sm text-black dark:text-white"
                                       >
                                           {plan.plan} -{" "}
                                           {plan.total_fee.toFixed(2)} kn
                                       </label>
                                   </div>
                               ))}
                           </div>
                           {errors?.workshopsWithMemberships?.[item.workshop_id]
                               ?.membership_plan && (
                               <div className="text-red-500 text-sm">
                                   {
                                       errors.workshopsWithMemberships[
                                           item.workshop_id
                                       ].membership_plan
                                   }
                               </div>
                           )}
                       </div>
                   );
               })}

               {/* Row 3: First Name & Last Name */}
               <div className="mb-5 flex flex-col gap-6 md:flex-row">
                   <div className="w-full xl:w-1/2">
                       <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                           Ime
                       </label>
                       <input
                           type="text"
                           value={data.first_name}
                           onChange={(e) =>
                               setData("first_name", e.target.value)
                           }
                           placeholder="Unos imena..."
                           className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white"
                       />
                       {errors.first_name && (
                           <div className="text-red-500 text-sm">
                               {errors.first_name}
                           </div>
                       )}
                   </div>

                   <div className="w-full xl:w-1/2">
                       <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                           Prezime
                       </label>
                       <input
                           type="text"
                           value={data.last_name}
                           onChange={(e) =>
                               setData("last_name", e.target.value)
                           }
                           placeholder="Unos prezimena..."
                           className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white"
                       />
                       {errors.last_name && (
                           <div className="text-red-500 text-sm">
                               {errors.last_name}
                           </div>
                       )}
                   </div>
               </div>

               {/* Row 4: Birth Year, Email, and Phone */}
               <div className="mb-5 flex flex-col gap-6 md:flex-row">
                   <div className="w-full xl:w-1/3">
                       <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                           Godina rođenja
                       </label>
                       <input
                           type="number"
                           value={data.birth_year}
                           onChange={(e) =>
                               setData("birth_year", e.target.value)
                           }
                           placeholder="Godina rođenja..."
                           className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 text-black outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                       />
                   </div>

                   <div className="w-full xl:w-1/3">
                       <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                           Email
                       </label>
                       <input
                           type="email"
                           value={data.email}
                           onChange={(e) => setData("email", e.target.value)}
                           placeholder="johndoe@mail.com"
                           className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 text-black outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                       />
                   </div>

                   <div className="w-full xl:w-1/3">
                       <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                           Telefon
                       </label>
                       <input
                           type="text"
                           value={data.phone_number}
                           onChange={(e) =>
                               setData("phone_number", e.target.value)
                           }
                           placeholder="051 555-011"
                           className="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 text-black outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input dark:text-white"
                       />
                   </div>
               </div>

               {/* Row 5: Active Status */}
               <div className="mb-5">
                   <label className="mr-2 text-sm font-medium text-black dark:text-white">
                       Aktivan upis?
                   </label>
                   <input
                       type="checkbox"
                       checked={data.is_active}
                       onChange={(e) => setData("is_active", e.target.checked)}
                   />
               </div>

               {/* Submit Button */}
               <button
                   type="submit"
                   disabled={processing}
                   className="flex w-full justify-center rounded bg-primary p-3 font-medium text-gray hover:bg-opacity-90"
               >
                   {processing ? "Spremam..." : submitButtonText}
               </button>
           </div>
       </form>
   );
        
};

export default MemberCreateForm;
