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
 * It also asserts that the Code block's language list (`codeLanguages`)
 * matches the block, all three renderers and the shared highlighter (#1044).
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
// Blocks the blade renderer ships as a static partial while React/Vue fall
// back to the server (DynamicBlock) at render time. They must appear in
// blade, but skip the React/Vue registration check.
const bladeOnly = new Set(manifest.bladeOnly ?? []);
const expectedForJs = new Set([...expected].filter((b) => !bladeOnly.has(b)));

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
        expectedForJs,
        checkJsRenderer(
            'react',
            'packages/visual-editor-renderer-react/src/blocks/registerCoreBlocks.ts'
        ).found
    ),
    diff(
        'vue',
        expectedForJs,
        checkJsRenderer(
            'vue',
            'packages/visual-editor-renderer-vue/src/blocks/registerCoreBlocks.ts'
        ).found
    ),
    diff('blade', expectedForBlade, checkBladeRenderer().found),
];

/** The quoted strings in the array assigned to the name in `marker`. */
function listAfter(relativePath, marker) {
    const src = fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
    const start = src.indexOf(marker);
    const open = src.indexOf('= [', start);
    const body = src.slice(open, src.indexOf(']', open));
    return [...body.matchAll(/'([a-z]+)'/g)].map((match) => match[1]);
}

function codeLanguageReports() {
    const languages = new Set(manifest.codeLanguages);
    const blockJson = JSON.parse(
        fs.readFileSync(path.join(repoRoot, 'resources/js/visual-editor/blocks/code/block.json'), 'utf8')
    );
    const sources = {
        'code block': blockJson.attributes.language.enum,
        'code block labels': [
            'plaintext',
            ...[
                ...fs
                    .readFileSync(path.join(repoRoot, 'resources/js/visual-editor/blocks/code/languages.ts'), 'utf8')
                    .matchAll(/value: '([a-z]+)'/g),
            ].map((match) => match[1]),
        ],
        'code languages (react)': listAfter('packages/visual-editor-renderer-react/src/support/codeLanguage.ts', 'CODE_LANGUAGES'),
        'code languages (vue)': listAfter('packages/visual-editor-renderer-vue/src/support/codeLanguage.ts', 'CODE_LANGUAGES'),
        'code languages (blade)': listAfter('packages/visual-editor-renderer-blade/src/Support/CodeLanguage.php', 'const LANGUAGES'),
        'code highlighter': ['plaintext', ...listAfter('packages/code-highlight/src/highlighter.js', 'SUPPORTED_LANGUAGES =')],
    };

    return Object.entries(sources).map(([label, list]) => diff(label, languages, new Set(list)));
}

reports.push(...codeLanguageReports());

let failed = false;
for (const report of reports) {
    if (report.missing.length === 0 && report.extra.length === 0) {
        if (report.label.startsWith('code ')) {
            console.log(`  ok   ${report.label}: all ${manifest.codeLanguages.length} languages listed`);
            continue;
        }
        const total = report.label === 'blade' ? expectedForBlade.size : expectedForJs.size;
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
