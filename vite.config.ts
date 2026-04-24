import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

// Legacy editor root — reference-only custom-React implementation retained
// under _legacy/ during the Gutenberg adoption. M1 introduces the sandbox
// entry at resources/js/visual-editor/sandbox/ while the new editor tree
// (M3+) will live at resources/js/visual-editor/editor/.
// See docs/gutenberg-adoption.md and issue #309.
const editorRoot = resolve(__dirname, 'resources/js/visual-editor/_legacy/editor');
const sandboxEntry = resolve(__dirname, 'resources/js/visual-editor/sandbox/main.tsx');
const visualEditorEntry = resolve(
    __dirname,
    'resources/js/visual-editor/editor/main.tsx'
);
// D1 (#368). Site-editor shell entry — distinct boot bundle so the post
// editor and site editor can ship independent chunks.
const siteEditorEntry = resolve(
    __dirname,
    'resources/js/visual-editor/site-editor/main.tsx'
);
const coreDataShim = resolve(
    __dirname,
    'resources/js/visual-editor/vendor/core-data-shim.ts'
);

export default defineConfig(({ command, mode }) => {
    const isLibraryBuild = mode === 'lib';

    return {
        plugins: [react()],
        root: command === 'serve' ? editorRoot : __dirname,
        resolve: {
            alias: {
                '@editor': editorRoot,
                // M2 (#312): every `@wordpress/core-data` import in the
                // editor bundle resolves to our in-repo empty-state shim.
                // cms-framework will replace the shim with a real
                // Laravel-backed `core` store in a later milestone.
                '@wordpress/core-data': coreDataShim,
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
                // App build — produces the bundled editor application plus the
                // M1 sandbox entry. The sandbox entry dynamically imports its
                // Gutenberg-using module so `@wordpress/*` lands in a dedicated
                // `gutenberg` chunk that is only fetched when the editor mounts.
                target: 'esnext',
                outDir: resolve(__dirname, 'dist/editor'),
                emptyOutDir: true,
                manifest: false,
                sourcemap: true,
                rollupOptions: {
                    input: {
                        editor: resolve(editorRoot, 'main.tsx'),
                        sandbox: sandboxEntry,
                        'visual-editor': visualEditorEntry,
                        'site-editor': siteEditorEntry,
                    },
                    output: {
                        format: 'es',
                        entryFileNames: '[name].js',
                        chunkFileNames: 'chunks/[name]-[hash].js',
                        assetFileNames: 'assets/[name]-[hash][extname]',
                        manualChunks(id) {
                            if (id.includes('/node_modules/@wordpress/')) {
                                return 'gutenberg';
                            }
                            return undefined;
                        },
                    },
                },
            },
    };
});
