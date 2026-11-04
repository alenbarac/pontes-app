import axios from "axios";

export async function saveMemberGenders(gendersById) {
    const members = Object.entries(gendersById)
        .filter(([, gender]) => gender === "male" || gender === "female")
        .map(([id, gender]) => ({
            id: Number(id),
            gender,
        }));

    await axios.post(route("members.genders"), { members });
}
