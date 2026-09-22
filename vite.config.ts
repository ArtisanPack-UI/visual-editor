import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

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
// #808 H4: `@wordpress/core-data` is now installed as a `file:`
// dependency pointing at `vendor-shims/core-data`, so node module
// resolution finds the shim under `node_modules/@wordpress/core-data`.
// The old Vite `resolve.alias` produced two module instances at dev
// runtime (see docs/wip/808-navigation-parity-notes.md), because the
// alias did not survive esbuild's dep pre-bundle pass.

// Several transitive `@wordpress/*` dependencies (e.g. `global-styles-engine`,
// `server-side-render`) ship their own nested `node_modules/@wordpress/blocks`
// copy. Without explicit aliases Rollup resolves each import chain to a
// different on-disk file, then `manualChunks` collocates all three copies
// into the single `gutenberg` chunk, where each copy re-registers Gutenberg's
// `core/blocks` Redux store at module init — surfacing as the
// `Store "core/blocks" is already registered.` error in consumer apps and
// triggering a Vite HMR-overlay → `location.reload()` cascade in dev.
//
// Pin every `@wordpress/*` import to the package's top-level node_modules
// copy so Rollup produces a single instance.
const sharedWordpressSingletons = [
    '@wordpress/blocks',
    '@wordpress/block-editor',
    '@wordpress/block-library',
    '@wordpress/components',
    '@wordpress/data',
    '@wordpress/element',
    '@wordpress/hooks',
    '@wordpress/i18n',
    // #717 — the inline-icon format registers via `@wordpress/rich-text`
    // and `@wordpress/format-library` registers the core formats against
    // the same rich-text store; pin rich-text to one instance so both
    // share a single format registry.
    '@wordpress/rich-text',
];
const wordpressSingletonAliases = Object.fromEntries(
    sharedWordpressSingletons.map((name) => [
        name,
        resolve(__dirname, 'node_modules', name),
    ]),
);

export default defineConfig(({ mode }) => {
    const isLibraryBuild = mode === 'lib';

    return {
        plugins: [react()],
        root: __dirname,
        // The app-mode build is consumed by Keystone (and other host apps)
        // by copying `dist/editor/*` to `public/visual-editor/`. The bundle
        // emits relative `chunks/...` imports plus `__vitePreload` CSS deps
        // that are resolved as `base + path`; without a base they resolve
        // to `/assets/...` and 404. Pinning the base keeps the prebuilt
        // self-contained at its hosted URL.
        base: isLibraryBuild ? '/' : '/visual-editor/',
        resolve: {
            alias: {
                ...wordpressSingletonAliases,
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
                    entry: visualEditorEntry,
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
