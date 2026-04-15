import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@editor': resolve(__dirname, 'resources/js/visual-editor/editor'),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: ['./resources/js/visual-editor/editor/test-setup.ts'],
        include: [
            'resources/js/visual-editor/**/*.{test,spec}.{ts,tsx}',
        ],
    },
});
