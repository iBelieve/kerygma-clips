import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/css/filament/app/theme.css",
                "resources/js/components/view-transcript.js",
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            preserveEntrySignatures: "exports-only",
        },
    },
    server: {
        host: "127.0.0.1",
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
