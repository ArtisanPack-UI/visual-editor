import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

// Legacy editor root — reference-only custom-React implementation retained
// under _legacy/ during the Gutenberg adoption. M1 will introduce a new
// editor tree at resources/js/visual-editor/editor/ and wire vite accordingly.
// See docs/gutenberg-adoption.md and issue #309.
const editorRoot = resolve(__dirname, 'resources/js/visual-editor/_legacy/editor');

export default defineConfig(({ command, mode }) => {
    const isLibraryBuild = mode === 'lib';

    return {
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
        build: isLibraryBuild
            ? {
                // Library build — produces an ES module that external packages
                // can import from. React, ReactDOM, and Zustand are externalized
                // so they come from the host application's node_modules.
                target: 'esnext',
                outDir: resolve(__dirname, 'dist/lib'),
                emptyOutDir: true,
                sourcemap: true,
                lib: {
                    entry: resolve(editorRoot, 'index.ts'),
                    formats: ['es'],
                    fileName: 'visual-editor',
                },
                rollupOptions: {
                    external: [
                        'react',
                        'react-dom',
                        'react/jsx-runtime',
                        'zustand',
                        '@tiptap/react',
                        '@tiptap/core',
                        '@tiptap/extension-paragraph',
                        '@tiptap/extension-heading',
                        '@tiptap/extension-bold',
                        '@tiptap/extension-italic',
                        '@tiptap/extension-link',
                        '@tiptap/extension-text',
                        '@tiptap/extension-document',
                        '@tiptap/extension-hard-break',
                    ],
                },
            }
            : {
                // App build — produces the full bundled editor application.
                target: 'esnext',
                outDir: resolve(__dirname, 'dist/editor'),
                emptyOutDir: true,
                manifest: false,
                sourcemap: true,
                rollupOptions: {
                    input: resolve(editorRoot, 'main.tsx'),
                    output: {
                        format: 'es',
                        entryFileNames: 'main.js',
                        chunkFileNames: '[name].js',
                        assetFileNames: '[name][extname]',
                    },
                },
            },
    };
});
