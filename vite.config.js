import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import { fileURLToPath } from 'node:url'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.jsx', 'resources/js/pdv/main.jsx'],
            refresh: true,
        }),
        react(),
    ],
    // "@/..." = resources/js (as telas dos módulos importam os componentes do núcleo assim)
    resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
    css: {
        preprocessorOptions: {
            // avisos do próprio Bootstrap com o Sass novo; não afetam o resultado
            scss: { quietDeps: true, silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'] },
        },
    },
})
