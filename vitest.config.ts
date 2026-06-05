import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            // Keep the test bundle aligned with the production Vite alias
            // (#312). Tests import `@wordpress/core-data` via the shim so
            // selector stubs stay in sync across both environments.
            '@wordpress/core-data': resolve(
                __dirname,
                'resources/js/visual-editor/vendor/core-data-shim.ts'
            ),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: ['./resources/js/visual-editor/test-setup.ts'],
        include: [
            'resources/js/visual-editor/**/*.{test,spec}.{ts,tsx}',
            'packages/**/tests/**/*.{test,spec}.{ts,tsx}',
        ],
    },
});
