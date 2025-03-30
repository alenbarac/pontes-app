import "../css/app.css";
import "./bootstrap";
import "simplebar-react/dist/simplebar.min.css";

import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import { Toaster } from "react-hot-toast";
import { ThemeProvider } from "@/context/ThemeContext"; // Adjust the path as needed

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx"),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <Toaster
                    position="top-center"
                    containerStyle={{
                        top: 90,
                    }}
                    reverseOrder={false}
                />
                <ThemeProvider>
                    <App {...props} />
                </ThemeProvider>
            </>,
        );
    },
    progress: { color: "#4B5563" },
});
