import "../css/app.css";
import "./bootstrap";

import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import { Toaster } from "react-hot-toast";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx"), // Ensures only `.jsx` files are loaded
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <Toaster position="top-center" reverseOrder={false} />
                <App {...props} />
            </>,
        );
    },
    progress: { color: "#4B5563" },
});
