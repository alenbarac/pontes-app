import flatpickr from "flatpickr";
import { Croatian } from "flatpickr/dist/l10n/hr.js";
import monthSelectPlugin from "flatpickr/dist/plugins/monthSelect/index.js";

// Flatpickr positions a calendar that does not fit by reading document.styleSheets.
// Cross-origin sheets (the app fonts) throw SecurityError on cssRules and the popup never opens.
const cssRulesDescriptor = Object.getOwnPropertyDescriptor(CSSStyleSheet.prototype, "cssRules");

if (cssRulesDescriptor?.get && cssRulesDescriptor.get.name !== "safeCssRules") {
    const readRules = cssRulesDescriptor.get;

    function safeCssRules() {
        try {
            return readRules.call(this);
        } catch {
            return null;
        }
    }

    Object.defineProperty(CSSStyleSheet.prototype, "cssRules", {
        configurable: true,
        enumerable: cssRulesDescriptor.enumerable,
        get: safeCssRules,
    });
}

flatpickr.localize(Croatian);

export { Croatian };

export function monthPickerOptions() {
    return {
        locale: Croatian,
        altInput: true,
        altFormat: "F Y",
        dateFormat: "Y-m",
        disableMobile: true,
        plugins: [
            monthSelectPlugin({
                shorthand: false,
                dateFormat: "Y-m",
                altFormat: "F Y",
            }),
        ],
    };
}
