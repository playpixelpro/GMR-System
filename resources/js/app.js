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


document.addEventListener("click", (e) => {
    const toggleBtn = e.target.closest("[data-password-toggle]");
    if (!toggleBtn) return;

    e.preventDefault();
    const targetSelector = toggleBtn.getAttribute("data-password-toggle");
    let input = targetSelector ? document.querySelector(targetSelector) : null;
    if (!input) {
        input = toggleBtn.parentElement?.querySelector("input");
    }
    if (!input) return;

    const isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";

    const eyeOpen = toggleBtn.querySelector(".password-eye-open");
    const eyeClosed = toggleBtn.querySelector(".password-eye-closed");

    if (eyeOpen && eyeClosed) {
        if (isPassword) {
            eyeOpen.classList.add("hidden");
            eyeClosed.classList.remove("hidden");
        } else {
            eyeOpen.classList.remove("hidden");
            eyeClosed.classList.add("hidden");
        }
    }
});
