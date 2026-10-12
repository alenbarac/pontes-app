import { useMemo } from "react";
import { useForm } from "@inertiajs/react";
import Button from "@/Components/ui/button/Button";
import Label from "../form/Label";
import Input from "../form/input/InputField";
import Select from "../form/Select";
import toast from "react-hot-toast";

function chargedAmount(baseAmount, percent) {
    const value = Number(baseAmount);
    const rate = Number(percent);

    if (!Number.isFinite(value) || !Number.isFinite(rate) || rate <= 0 || rate >= 100) {
        return null;
    }

    return (value * (1 - rate / 100)).toFixed(2);
}

export default function WorkshopDiscountForm({
    workshops = [],
    basePlans = {},
    discount = null,
    onClose,
}) {
    const isEdit = Boolean(discount);
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        workshop_id: discount ? String(discount.workshop_id) : "",
        membership_plan_id: "",
        percent: discount?.percent != null ? String(discount.percent) : "",
    });

    const plansForWorkshop = useMemo(() => {
        if (!data.workshop_id) {
            return [];
        }

        return basePlans[data.workshop_id] || basePlans[Number(data.workshop_id)] || [];
    }, [basePlans, data.workshop_id]);

    const selectedBase = plansForWorkshop.find(
        (plan) => String(plan.id) === String(data.membership_plan_id),
    );
    const previewBase = isEdit ? discount.base_amount : selectedBase?.total_fee;
    const preview = chargedAmount(previewBase, data.percent);

    const handleSubmit = (e) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(isEdit ? "Popust je ažuriran." : "Popust je dodan.");
                reset();
                onClose?.();
            },
            onError: () => {
                toast.error("Došlo je do pogreške prilikom spremanja.");
            },
        };

        if (isEdit) {
            patch(route("workshop-discounts.update", discount.id), options);
            return;
        }

        post(route("workshop-discounts.store"), options);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div>
                <Label htmlFor="workshop_id">Radionica</Label>
                {isEdit ? (
                    <Input
                        id="workshop_id"
                        value={discount.workshop_name || ""}
                        disabled
                    />
                ) : (
                    <Select
                        id="workshop_id"
                        value={data.workshop_id}
                        onChange={(value) => {
                            setData({
                                ...data,
                                workshop_id: value,
                                membership_plan_id: "",
                            });
                        }}
                        options={workshops.map((workshop) => ({
                            value: String(workshop.id),
                            label: workshop.name,
                        }))}
                        placeholder="Odaberite radionicu"
                    />
                )}
                {errors.workshop_id && (
                    <p className="text-red-500 text-sm">{errors.workshop_id}</p>
                )}
            </div>

            <div>
                <Label htmlFor="membership_plan_id">Plan članarine</Label>
                {isEdit ? (
                    <Input id="membership_plan_id" value={discount.base_plan || ""} disabled />
                ) : (
                    <Select
                        id="membership_plan_id"
                        value={data.membership_plan_id}
                        onChange={(value) => setData("membership_plan_id", value)}
                        options={plansForWorkshop.map((plan) => ({
                            value: String(plan.id),
                            label: `${plan.plan} (${Number(plan.total_fee).toFixed(2)} EUR)`,
                        }))}
                        placeholder="Odaberite plan"
                    />
                )}
                {errors.membership_plan_id && (
                    <p className="text-red-500 text-sm">{errors.membership_plan_id}</p>
                )}
            </div>

            <div>
                <Label htmlFor="percent">Popust (%)</Label>
                <Input
                    type="number"
                    id="percent"
                    min="1"
                    max="99"
                    step={1}
                    placeholder="50"
                    value={data.percent}
                    onChange={(e) => setData("percent", e.target.value)}
                />
                {errors.percent && (
                    <p className="text-red-500 text-sm">{errors.percent}</p>
                )}
            </div>

            {preview && (
                <p className="text-sm text-gray-600 dark:text-gray-300">
                    Iznos s popustom: <strong>{preview} EUR</strong>
                    {previewBase != null && (
                        <span> (od {Number(previewBase).toFixed(2)} EUR)</span>
                    )}
                </p>
            )}

            <div className="flex justify-end gap-2">
                {onClose && (
                    <Button type="button" variant="outline" onClick={onClose}>
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
