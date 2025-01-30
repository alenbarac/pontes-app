import React, { useState, useEffect, useRef } from "react";

interface Option {
    id: number;
    text: string;
}

interface MultiSelectProps {
    options: Option[]; // Available options
    value: number[]; // Selected option IDs
    onChange: (value: number[]) => void; // Callback to parent
    placeholder?: string;
}

const MultiSelect: React.FC<MultiSelectProps> = ({
    options,
    value,
    onChange,
    placeholder = "Select an option",
}) => {
    const [selected, setSelected] = useState<number[]>(value);
    const [show, setShow] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setSelected(value); // Sync state with parent value
    }, [value]);

    const toggleOption = (id: number) => {
        const updatedSelection = selected.includes(id)
            ? selected.filter((item) => item !== id)
            : [...selected, id];

        setSelected(updatedSelection);
        onChange(updatedSelection); // Notify parent of changes
    };

    const isSelected = (id: number) => selected.includes(id);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(event.target as Node)
            ) {
                setShow(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () =>
            document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    return (
        <div className="relative w-full" ref={dropdownRef}>
            <div
                className="flex items-center justify-between border border-stroke bg-white px-3 py-2 rounded-md cursor-pointer dark:bg-form-input dark:border-form-strokedark"
                onClick={() => setShow(!show)}
            >
                <div className="flex flex-wrap gap-2">
                    {selected.length > 0 ? (
                        selected.map((id) => {
                            const selectedOption = options.find(
                                (option) => option.id === id,
                            );
                            return (
                                selectedOption && (
                                    <span
                                        key={id}
                                        className="my-1.5 flex items-center justify-center rounded border-[.5px] border-stroke bg-gray px-2.5 py-1.5 text-sm font-medium dark:border-strokedark dark:bg-white/30"
                                    >
                                        {selectedOption.text}
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                toggleOption(id);
                                            }}
                                            className="text-red-500"
                                        >
                                            ×
                                        </button>
                                    </span>
                                )
                            );
                        })
                    ) : (
                        <span className="text-gray-500">{placeholder}</span>
                    )}
                </div>
                <svg
                    className={`w-4 h-4 transform transition-transform ${
                        show ? "rotate-180" : ""
                    }`}
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M19 9l-7 7-7-7"
                    />
                </svg>
            </div>

            {show && (
                <div className="absolute top-full left-0 z-10 w-full mt-2 bg-white border border-stroke rounded-md shadow-md dark:bg-form-input dark:border-form-strokedark">
                    {options.length > 0 ? (
                        options.map((option) => (
                            <div
                                key={option.id}
                                className={`flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 ${
                                    isSelected(option.id)
                                        ? "bg-gray-200 dark:bg-gray-800"
                                        : ""
                                }`}
                                onClick={() => toggleOption(option.id)}
                            >
                                <input
                                    type="checkbox"
                                    checked={isSelected(option.id)}
                                    onChange={() => toggleOption(option.id)}
                                    className="form-checkbox"
                                />
                                <span>{option.text}</span>
                            </div>
                        ))
                    ) : (
                        <div className="p-3 text-center text-gray-500">
                            No options available
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default MultiSelect;
