import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

// Legacy editor tests live under _legacy/ during the Gutenberg adoption.
// M1 will introduce new tests under resources/js/visual-editor/editor/ and
// update this config. See docs/gutenberg-adoption.md.
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@editor': resolve(__dirname, 'resources/js/visual-editor/_legacy/editor'),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: ['./resources/js/visual-editor/_legacy/editor/test-setup.ts'],
        include: [
            'resources/js/visual-editor/**/*.{test,spec}.{ts,tsx}',
        ],
    },
});
