#!/usr/bin/env node
/**
 * Renderer-parity check.
 *
 * Asserts that every block name in `packages/renderer-parity.json` is
 * registered by each of the three renderer packages:
 *
 *  - packages/visual-editor-renderer-react/src/blocks/registerCoreBlocks.ts
 *  - packages/visual-editor-renderer-vue/src/blocks/registerCoreBlocks.ts
 *  - packages/visual-editor-renderer-blade/resources/views/blocks/{ns}/{block}.blade.php
 *
 * Exits non-zero on the first mismatch — wire this into CI so any new fork
 * (artisanpack/*) or removed block trips the build before merge.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, '..');

const manifestPath = path.join(repoRoot, 'packages', 'renderer-parity.json');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const expected = new Set(manifest.blocks);
// Blocks handled by the blade renderer via the dynamic pipeline (query
// inlining, dynamic block registry) instead of a static partial. They
// must still appear in React/Vue, but skip the blade partial check.
const bladeDynamic = new Set(manifest.bladeDynamic ?? []);
const expectedForBlade = new Set([...expected].filter((b) => !bladeDynamic.has(b)));

function extractRegistered(jsSource) {
    // Match `'ns/name': SomeRenderer,` entries inside the CORE_BLOCKS
    // record. The fork's `// Fork:` comment lines don't have quotes so
    // the regex naturally skips them.
    const regex = /['"]([a-z][a-z0-9-]*\/[a-z0-9-]+)['"]\s*:\s*[A-Z]/g;
    const found = new Set();
    let match;
    while ((match = regex.exec(jsSource)) !== null) {
        found.add(match[1]);
    }
    return found;
}

function checkJsRenderer(name, relativePath) {
    const src = fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
    const found = extractRegistered(src);
    return { name, found };
}

function checkBladeRenderer() {
    const root = path.join(
        repoRoot,
        'packages',
        'visual-editor-renderer-blade',
        'resources',
        'views',
        'blocks'
    );
    const found = new Set();
    if (!fs.existsSync(root)) {
        return { name: 'blade', found };
    }
    for (const namespaceDir of fs.readdirSync(root, { withFileTypes: true })) {
        if (!namespaceDir.isDirectory()) continue;
        const ns = namespaceDir.name;
        const nsDir = path.join(root, ns);
        for (const file of fs.readdirSync(nsDir)) {
            if (!file.endsWith('.blade.php')) continue;
            const block = file.replace(/\.blade\.php$/, '');
            found.add(`${ns}/${block}`);
        }
    }
    return { name: 'blade', found };
}

function diff(label, expected, found) {
    const missing = [...expected].filter((b) => !found.has(b));
    const extra = [...found].filter((b) => !expected.has(b));
    return { label, missing, extra };
}

const reports = [
    diff(
        'react',
        expected,
        checkJsRenderer(
            'react',
            'packages/visual-editor-renderer-react/src/blocks/registerCoreBlocks.ts'
        ).found
    ),
    diff(
        'vue',
        expected,
        checkJsRenderer(
            'vue',
            'packages/visual-editor-renderer-vue/src/blocks/registerCoreBlocks.ts'
        ).found
    ),
    diff('blade', expectedForBlade, checkBladeRenderer().found),
];

let failed = false;
for (const report of reports) {
    if (report.missing.length === 0 && report.extra.length === 0) {
        const total = report.label === 'blade' ? expectedForBlade.size : expected.size;
        console.log(`  ok   ${report.label}: all ${total} blocks registered`);
        continue;
    }
    failed = true;
    console.error(`  fail ${report.label}:`);
    if (report.missing.length) {
        console.error(`    missing: ${report.missing.join(', ')}`);
    }
    if (report.extra.length) {
        console.error(`    extra:   ${report.extra.join(', ')}`);
    }
}

if (failed) {
    console.error('\nRenderer parity check failed. Update the renderer or packages/renderer-parity.json.');
    process.exit(1);
}

console.log('\nRenderer parity OK.');
