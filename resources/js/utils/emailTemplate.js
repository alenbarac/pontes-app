export function fillEmailTemplate(template, values = {}) {
    let text = template ?? "";
    const ime = String(values.ime ?? "").trim();
    const studio = String(values.studio ?? "").trim();
    const opis = String(values.opis ?? "").trim();
    const nazivDokumenta = String(values.naziv_dokumenta ?? "").trim();

    if (ime === "") {
        text = text.replace(/\s*\/na\s*\{ime\}/g, "");
    }

    if (studio === "") {
        text = text.replaceAll(" - {studio}", "");
    }

    if (nazivDokumenta === "") {
        text = text.replaceAll("{naziv_dokumenta} - ", "");
        text = text.replaceAll(" - {naziv_dokumenta}", "");
    }

    text = text
        .replaceAll("{ime}", ime)
        .replaceAll("{opis}", opis)
        .replaceAll("{studio}", studio)
        .replaceAll("{naziv_dokumenta}", nazivDokumenta);

    return text
        .replace(/[ \t]{2,}/g, " ")
        .replace(/[ \t]+([,!.])/g, "$1")
        .trim();
}
