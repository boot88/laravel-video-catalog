import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Для проверки на реальном телефоне: `npm run dev:lan` подставляет LAN-IP,
// и laravel-vite-plugin пишет его же в public/hot (иначе там оказывается
// localhost/127.0.0.1, который с телефона указывает на сам телефон).
const devHost = process.env.VITE_DEV_HOST || 'localhost';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: devHost,
        port: Number(process.env.VITE_PORT || 5173),
        strictPort: true,
        // Страница отдаётся с :8002, а модули — с :5173, это кросс-домен:
        // без CORS браузер на телефоне не загрузит CSS/JS.
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
