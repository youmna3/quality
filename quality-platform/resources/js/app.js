import "./bootstrap";
import "../css/app.css";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

const showFatalError = (error) => {
    const message =
        error instanceof Error
            ? `${error.name}: ${error.message}`
            : String(error);
    // Replace blank screens with a visible runtime error to speed up debugging.
    document.body.innerHTML = `
        <div style="font-family: ui-sans-serif, system-ui; padding: 24px; background: #fff; color: #111827;">
            <h1 style="font-size: 20px; margin: 0 0 12px;">Frontend Runtime Error</h1>
            <pre style="white-space: pre-wrap; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px;">${message}</pre>
            <p style="margin-top: 12px; color: #4b5563;">Open browser console for full stack trace.</p>
        </div>
    `;
};

window.addEventListener("error", (event) => {
    if (event?.error) {
        showFatalError(event.error);
    }
});

window.addEventListener("unhandledrejection", (event) => {
    showFatalError(event.reason);
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.errorHandler = (error) => showFatalError(error);

        return app
            .use(plugin)
            .use(ZiggyVue, props.initialPage.props.ziggy)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
