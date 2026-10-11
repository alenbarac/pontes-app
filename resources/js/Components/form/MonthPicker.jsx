import { useMemo } from "react";
import Flatpickr from "react-flatpickr";
import { monthPickerOptions } from "@/lib/flatpickrLocale";

export default function MonthPicker({
    value,
    onChange,
    id,
    className,
    placeholder = "Odaberite mjesec",
    disabled = false,
}) {
    const options = useMemo(() => monthPickerOptions(), []);

    return (
        <Flatpickr
            id={id}
            value={value || ""}
            disabled={disabled}
            options={options}
            onChange={(dates) => {
                if (!dates?.length) {
                    onChange("");
                    return;
                }

                const date = dates[0];
                const month = String(date.getMonth() + 1).padStart(2, "0");
                onChange(`${date.getFullYear()}-${month}`);
            }}
            placeholder={placeholder}
            className={className}
        />
    );
}
