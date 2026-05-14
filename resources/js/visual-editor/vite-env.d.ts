/// <reference types="vite/client" />

// Pulls in Vite's ambient module declarations for asset imports — most
// importantly `*.css?inline`, which `editor/canvas-styles.ts` uses to
// resolve stylesheets to their CSS text for injection into the editor's
// `BlockCanvas` iframe (#347). Without this reference TypeScript can't
// resolve the `?inline` query suffix.
