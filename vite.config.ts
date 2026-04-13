import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@spike': resolve(__dirname, 'resources/js/editor-spike'),
        },
    },
    build: {
        target: 'esnext',
        outDir: 'public/editor-spike',
        emptyOutDir: true,
        manifest: false,
        rollupOptions: {
            input: resolve(__dirname, 'resources/js/editor-spike/main.tsx'),
            output: {
                entryFileNames: 'main.js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name][extname]',
            },
        },
    },
});
