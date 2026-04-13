import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

const editorRoot = resolve(__dirname, 'resources/js/visual-editor/editor');

export default defineConfig(({ command }) => ({
    plugins: [react()],
    root: command === 'serve' ? editorRoot : __dirname,
    resolve: {
        alias: {
            '@editor': editorRoot,
        },
    },
    server: {
        port: 5175,
        strictPort: false,
    },
    build: {
        target: 'esnext',
        outDir: resolve(__dirname, 'dist/editor'),
        emptyOutDir: true,
        manifest: false,
        sourcemap: true,
        rollupOptions: {
            input: resolve(editorRoot, 'main.tsx'),
            external: [
                'react',
                'react/jsx-runtime',
                'react/jsx-dev-runtime',
                'react-dom',
                'react-dom/client',
            ],
            output: {
                format: 'es',
                entryFileNames: 'main.js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name][extname]',
            },
        },
    },
}));
