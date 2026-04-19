#!/usr/bin/env node
/**
 * Placeholder .pot extractor for the visual editor (#312, M2).
 *
 * Scans `resources/js/visual-editor/**\/*.{ts,tsx}` for calls to the
 * `@wordpress/i18n` translation functions (__, _x, _n, _nx) bound to the
 * `artisanpack-visual-editor` text domain and emits a gettext .pot file
 * at `languages/artisanpack-visual-editor.pot`.
 *
 * This is deliberately minimal — it exists so translators have a baseline
 * template to work from during the Gutenberg adoption and so contributors
 * have a reproducible command. A richer extractor (plural forms, context
 * comments, PHP scanning, deduping with source-line accumulation) will
 * replace it when translation work ramps up post-V1.
 */

import { readdir, readFile, writeFile, mkdir } from 'node:fs/promises';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const SOURCE_DIR = join(ROOT, 'resources/js/visual-editor');
const OUTPUT_DIR = join(ROOT, 'languages');
const OUTPUT_FILE = join(OUTPUT_DIR, 'artisanpack-visual-editor.pot');
const TEXT_DOMAIN = 'artisanpack-visual-editor';

// `_legacy` holds reference-only code deleted at V1 ship; skip it so stale
// translations don't pollute the .pot. node_modules and dist are never scanned.
// `resources/js/visual-editor/vendor/` holds in-repo shims — those DO contain
// editor-facing translatable strings, so it stays in scope.
const IGNORED_DIRS = new Set(['_legacy', 'node_modules', 'dist']);

/** @param {string} dir */
async function collectSourceFiles(dir) {
    /** @type {string[]} */
    const found = [];
    const entries = await readdir(dir, { withFileTypes: true });

    for (const entry of entries) {
        if (entry.isDirectory()) {
            if (IGNORED_DIRS.has(entry.name)) {
                continue;
            }
            found.push(...(await collectSourceFiles(join(dir, entry.name))));
            continue;
        }
        if (/\.(ts|tsx)$/.test(entry.name)) {
            found.push(join(dir, entry.name));
        }
    }

    return found;
}

/**
 * Match `__('msg', 'domain')`, `_x('msg', 'ctx', 'domain')`,
 * `_n('one', 'many', count, 'domain')`, `_nx('one', 'many', count, 'ctx', 'domain')`.
 * Keep the regex conservative — only single-line literal string args with the
 * expected text domain are picked up. Template strings and concatenations are
 * silently skipped; the richer extractor will handle those later.
 */
/**
 * The text-domain argument may be either a literal string
 * (`'artisanpack-visual-editor'` or `"artisanpack-visual-editor"`) or the
 * `TEXT_DOMAIN` identifier re-exported from `vendor/i18n.ts`. Both literal
 * forms are listed explicitly — embedding a capture-group alternation would
 * shift backreference numbering when the fragment is spliced into a larger
 * pattern that already captures a quote. The richer extractor (post-V1) will
 * resolve identifiers through the TS AST instead of lexical pattern matching.
 */
const DOMAIN = `(?:'artisanpack-visual-editor'|"artisanpack-visual-editor"|TEXT_DOMAIN)`;

const CALL_PATTERNS = [
    {
        fn: '__',
        regex: new RegExp(
            `\\b__\\(\\s*(['"])((?:\\\\\\1|(?!\\1).)*?)\\1\\s*,\\s*${DOMAIN}\\s*\\)`,
            'g'
        ),
        groups: { msgid: 2 },
    },
    {
        fn: '_x',
        regex: new RegExp(
            `\\b_x\\(\\s*(['"])((?:\\\\\\1|(?!\\1).)*?)\\1\\s*,\\s*(['"])((?:\\\\\\3|(?!\\3).)*?)\\3\\s*,\\s*${DOMAIN}\\s*\\)`,
            'g'
        ),
        groups: { msgid: 2, msgctxt: 4 },
    },
    {
        fn: '_n',
        regex: new RegExp(
            `\\b_n\\(\\s*(['"])((?:\\\\\\1|(?!\\1).)*?)\\1\\s*,\\s*(['"])((?:\\\\\\3|(?!\\3).)*?)\\3\\s*,\\s*[^,]+,\\s*${DOMAIN}\\s*\\)`,
            'g'
        ),
        groups: { msgid: 2, msgid_plural: 4 },
    },
    {
        fn: '_nx',
        regex: new RegExp(
            `\\b_nx\\(\\s*(['"])((?:\\\\\\1|(?!\\1).)*?)\\1\\s*,\\s*(['"])((?:\\\\\\3|(?!\\3).)*?)\\3\\s*,\\s*[^,]+,\\s*(['"])((?:\\\\\\5|(?!\\5).)*?)\\5\\s*,\\s*${DOMAIN}\\s*\\)`,
            'g'
        ),
        groups: { msgid: 2, msgid_plural: 4, msgctxt: 6 },
    },
];

/**
 * @param {string} file
 * @param {string} source
 * @param {number} offset
 */
function lineOf(source, offset) {
    let line = 1;
    for (let i = 0; i < offset && i < source.length; i += 1) {
        if (source[i] === '\n') {
            line += 1;
        }
    }
    return line;
}

/**
 * Strip `//` line comments and `/* … *\/` block comments so translation call
 * examples embedded in JSDoc don't show up in the .pot. The replacement keeps
 * newlines intact so line-number references stay accurate.
 *
 * @param {string} source
 * @returns {string}
 */
function stripComments(source) {
    return source
        .replace(/\/\*[\s\S]*?\*\//g, (match) =>
            match.replace(/[^\n]/g, ' ')
        )
        .replace(/(^|[^:])\/\/[^\n]*/g, (_match, prefix) => prefix);
}

function escapePotString(value) {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\t/g, '\\t');
}

/** @type {Map<string, { msgid: string; msgctxt?: string; msgid_plural?: string; references: Set<string> }>} */
const entries = new Map();

function keyFor(entry) {
    return `${entry.msgctxt ?? ''}\u0004${entry.msgid}`;
}

async function extract() {
    const files = await collectSourceFiles(SOURCE_DIR);

    for (const file of files) {
        const raw = await readFile(file, 'utf8');
        const source = stripComments(raw);
        const rel = relative(ROOT, file);

        for (const pattern of CALL_PATTERNS) {
            pattern.regex.lastIndex = 0;
            let match;
            while ((match = pattern.regex.exec(source)) !== null) {
                const entry = {
                    msgid: match[pattern.groups.msgid],
                    msgctxt: pattern.groups.msgctxt
                        ? match[pattern.groups.msgctxt]
                        : undefined,
                    msgid_plural: pattern.groups.msgid_plural
                        ? match[pattern.groups.msgid_plural]
                        : undefined,
                };
                const key = keyFor(entry);
                const existing = entries.get(key);
                const reference = `${rel}:${lineOf(source, match.index)}`;
                if (existing) {
                    existing.references.add(reference);
                } else {
                    entries.set(key, {
                        msgid: entry.msgid,
                        msgctxt: entry.msgctxt,
                        msgid_plural: entry.msgid_plural,
                        references: new Set([reference]),
                    });
                }
            }
        }
    }

    return entries;
}

function renderPot(entries) {
    const now = new Date().toISOString().replace(/\.\d{3}Z$/, '+0000');
    const header = [
        '# Copyright (C) Jacob Martella',
        '# This file is distributed under the same license as the ArtisanPack UI Visual Editor package.',
        'msgid ""',
        'msgstr ""',
        `"Project-Id-Version: artisanpack-ui-visual-editor\\n"`,
        `"POT-Creation-Date: ${now}\\n"`,
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        `"X-Domain: ${TEXT_DOMAIN}\\n"`,
        '',
    ].join('\n');

    const body = [...entries.values()]
        .sort((a, b) => a.msgid.localeCompare(b.msgid))
        .map((entry) => {
            const lines = [];
            for (const ref of [...entry.references].sort()) {
                lines.push(`#: ${ref}`);
            }
            if (entry.msgctxt !== undefined) {
                lines.push(`msgctxt "${escapePotString(entry.msgctxt)}"`);
            }
            lines.push(`msgid "${escapePotString(entry.msgid)}"`);
            if (entry.msgid_plural !== undefined) {
                lines.push(
                    `msgid_plural "${escapePotString(entry.msgid_plural)}"`
                );
                lines.push('msgstr[0] ""');
                lines.push('msgstr[1] ""');
            } else {
                lines.push('msgstr ""');
            }
            return lines.join('\n');
        })
        .join('\n\n');

    return `${header}\n${body}\n`;
}

async function main() {
    await mkdir(OUTPUT_DIR, { recursive: true });
    const collected = await extract();
    const output = renderPot(collected);
    await writeFile(OUTPUT_FILE, output, 'utf8');
    const target = relative(ROOT, OUTPUT_FILE);
    console.log(
        `Wrote ${collected.size} translation string(s) to ${target}.`
    );
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
