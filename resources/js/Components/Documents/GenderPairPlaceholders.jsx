import { useState } from "react";
import Label from "@/Components/form/Label";
import Input from "@/Components/form/input/InputField";
import Button from "@/Components/ui/button/Button";

const PRESETS = [
    { male: "član", female: "članica", label: "Član / članica" },
    { male: "učenik", female: "učenica", label: "Učenik / učenica" },
    { male: "polaznik", female: "polaznica", label: "Polaznik / polaznica" },
];

export function genderPairToken(male, female) {
    return `{{g:${male}|${female}}}`;
}

export default function GenderPairPlaceholders({ onInsert }) {
    const [male, setMale] = useState("");
    const [female, setFemale] = useState("");

    const insertCustom = () => {
        const maleForm = male.trim();
        const femaleForm = female.trim();
        if (maleForm === "" || femaleForm === "") {
            return;
        }
        onInsert(genderPairToken(maleForm, femaleForm));
        setMale("");
        setFemale("");
    };

    return (
        <div className="mt-4">
            <Label>Oblici prema spolu</Label>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Umetnite par. Za muškog člana ostaje prvi oblik, za ženskog drugi.
                Isti zapis pokriva i padeže i glagole, npr. sudjelovao / sudjelovala.
            </p>
            <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                {PRESETS.map((preset) => (
                    <button
                        key={preset.label}
                        type="button"
                        onClick={() => onInsert(genderPairToken(preset.male, preset.female))}
                        className="rounded border border-gray-300 px-3 py-2 text-left text-sm transition hover:border-brand-500 hover:bg-brand-50 dark:border-gray-700 dark:hover:bg-brand-900/20"
                    >
                        <code className="text-brand-600 dark:text-brand-400">
                            {genderPairToken(preset.male, preset.female)}
                        </code>
                        <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{preset.label}</div>
                    </button>
                ))}
            </div>
            <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <div>
                    <Label htmlFor="gender_pair_male">Muški oblik</Label>
                    <Input
                        id="gender_pair_male"
                        value={male}
                        placeholder="npr. sudjelovao"
                        onChange={(event) => setMale(event.target.value)}
                    />
                </div>
                <div>
                    <Label htmlFor="gender_pair_female">Ženski oblik</Label>
                    <Input
                        id="gender_pair_female"
                        value={female}
                        placeholder="npr. sudjelovala"
                        onChange={(event) => setFemale(event.target.value)}
                    />
                </div>
                <Button type="button" variant="outline" size="sm" onClick={insertCustom}>
                    Umetni par
                </Button>
            </div>
        </div>
    );
}
