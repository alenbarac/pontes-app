export default function GenderPrompt({ members, value, onChange }) {
    const setAllRemaining = (gender) => {
        const next = { ...value };
        members.forEach((member) => {
            if (!next[member.id]) {
                next[member.id] = gender;
            }
        });
        onChange(next);
    };

    return (
        <div className="space-y-3">
            <p className="text-sm text-gray-600 dark:text-gray-400">
                Predložak koristi oblike prema spolu. Odabir se sprema na člana.
            </p>
            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    onClick={() => setAllRemaining("male")}
                >
                    Označi sve preostale kao muški
                </button>
                <button
                    type="button"
                    className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    onClick={() => setAllRemaining("female")}
                >
                    Označi sve preostale kao ženski
                </button>
            </div>
            <ul className="max-h-56 space-y-2 overflow-y-auto">
                {members.map((member) => (
                    <li
                        key={member.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700"
                    >
                        <span className="text-sm text-gray-800 dark:text-white/90">
                            {member.first_name} {member.last_name}
                        </span>
                        <span className="flex gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <label className="inline-flex items-center gap-1.5">
                                <input
                                    type="radio"
                                    name={`gender-${member.id}`}
                                    checked={value[member.id] === "male"}
                                    onChange={() => onChange({ ...value, [member.id]: "male" })}
                                />
                                Muški
                            </label>
                            <label className="inline-flex items-center gap-1.5">
                                <input
                                    type="radio"
                                    name={`gender-${member.id}`}
                                    checked={value[member.id] === "female"}
                                    onChange={() => onChange({ ...value, [member.id]: "female" })}
                                />
                                Ženski
                            </label>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
