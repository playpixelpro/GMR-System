import "flyonui/flyonui";
import flatpickr from "flatpickr";

window.flatpickr = flatpickr;

const initializeFlyonUI = () => {
    window.HSStaticMethods?.autoInit();
    window.HSOverlay?.autoInit();
    window.HSDropdown?.autoInit();
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeFlyonUI, {
        once: true,
    });
} else {
    initializeFlyonUI();
}

