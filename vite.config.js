import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';  // ← AGGIUNGI QUESTA RIGA MANCANTE!

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/calendar.jsx', // ← Aggiungi questa riga
                'resources/css/calendar.css'  // ← E questa
            ],
            refresh: true,
        }),
        react(), // ← Assicurati che ci sia il plugin React
    ],
});
