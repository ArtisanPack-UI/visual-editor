import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@spike': resolve(__dirname, 'resources/js/editor-spike'),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: ['./resources/js/editor-spike/test-setup.ts'],
        include: ['resources/js/editor-spike/**/*.{test,spec}.{ts,tsx}'],
    },
});
