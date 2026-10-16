#!/usr/bin/env node
/**
 * .pot extractor for the visual editor (#312, M2; AST rewrite M-12).
 *
 * Scans `resources/js/visual-editor/**\/*.{ts,tsx}` for calls to the
 * `@wordpress/i18n` translation functions (__, _x, _n, _nx) bound to the
 * `artisanpack-visual-editor` text domain and emits a gettext .pot file
 * at `languages/artisanpack-visual-editor.pot`.
 *
 * Calls are found by walking each file's TypeScript AST, so multi-line
 * calls, trailing commas, comments between arguments, nested calls in the
 * `_n` count argument and no-substitution template literals are all read
 * the same way the compiler reads them. `/* translators: … *\/` and
 * `// translators: …` comments are attached the way `wp i18n make-pot`
 * does: the nearest comment before the call, on the same or the previous
 * line. A package-domain call whose msgid, plural or context isn't a
 * static string is reported on stderr and skipped.
 *
 * The extraction functions are exported so the Vitest suite can run them
 * in memory; the file only writes the .pot when executed directly.
 */

import { readdir, readFile, writeFile, mkdir } from 'node:fs/promises';
import { join, relative, sep } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import ts from 'typescript';

export const ROOT = fileURLToPath(new URL('..', import.meta.url));
export const SOURCE_DIR = join(ROOT, 'resources/js/visual-editor');
const OUTPUT_DIR = join(ROOT, 'languages');
const OUTPUT_FILE = join(OUTPUT_DIR, 'artisanpack-visual-editor.pot');
export const TEXT_DOMAIN = 'artisanpack-visual-editor';

// `_legacy` holds reference-only code deleted at V1 ship; skip it so stale
// translations don't pollute the .pot. node_modules and dist are never scanned.
// `resources/js/visual-editor/vendor/` holds in-repo shims — those DO contain
// editor-facing translatable strings, so it stays in scope.
const IGNORED_DIRS = new Set(['_legacy', 'node_modules', 'dist']);

/**
 * Argument positions for each i18n function. `domain` is the index of the
 * text-domain argument; the call only counts when it is `TEXT_DOMAIN` or
 * the literal package domain.
 */
const SIGNATURES = {
    __: { msgid: 0, domain: 1 },
    _x: { msgid: 0, msgctxt: 1, domain: 2 },
    _n: { msgid: 0, msgid_plural: 1, domain: 3 },
    _nx: { msgid: 0, msgid_plural: 1, msgctxt: 3, domain: 4 },
};

/** @param {string} dir */
export async function collectSourceFiles(dir) {
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
 * The i18n function a call invokes: a bare `__(…)` or a namespace /
 * member call such as `i18n.__(…)`.
 *
 * @param {ts.CallExpression} call
 * @returns {keyof typeof SIGNATURES | undefined}
 */
function calleeName(call) {
    let callee = call.expression;
    while (ts.isParenthesizedExpression(callee)) {
        callee = callee.expression;
    }
    const name = ts.isIdentifier(callee)
        ? callee.text
        : ts.isPropertyAccessExpression(callee)
          ? callee.name.text
          : undefined;

    return name !== undefined && Object.hasOwn(SIGNATURES, name)
        ? /** @type {keyof typeof SIGNATURES} */ (name)
        : undefined;
}

/**
 * The decoded value of a static string argument, or undefined when the
 * argument is anything else (a variable, a concatenation, a template with
 * placeholders).
 *
 * @param {ts.Expression | undefined} node
 * @returns {string | undefined}
 */
function staticString(node) {
    while (
        node &&
        (ts.isParenthesizedExpression(node) ||
            ts.isAsExpression(node) ||
            ts.isSatisfiesExpression(node))
    ) {
        node = node.expression;
    }
    if (
        node &&
        (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node))
    ) {
        return node.text;
    }
    return undefined;
}

/** @param {ts.Expression | undefined} node */
function isPackageDomain(node) {
    if (node && ts.isIdentifier(node)) {
        return node.text === 'TEXT_DOMAIN';
    }
    return staticString(node) === TEXT_DOMAIN;
}

/**
 * Every comment in the file, sorted by position. Comment trivia is read
 * around every token, then anything that falls inside JSX text (which is
 * markup, not trivia) is dropped.
 *
 * @param {ts.SourceFile} sourceFile
 * @returns {Array<{ pos: number; end: number; text: string }>}
 */
function collectComments(sourceFile) {
    const text = sourceFile.text;
    /** @type {Map<number, { pos: number; end: number; text: string }>} */
    const found = new Map();
    /** @type {Array<[number, number]>} */
    const jsxTextRanges = [];

    /** @param {ts.CommentRange[] | undefined} ranges */
    const add = (ranges) => {
        for (const range of ranges ?? []) {
            if (!found.has(range.pos)) {
                found.set(range.pos, {
                    pos: range.pos,
                    end: range.end,
                    text: text.slice(range.pos, range.end),
                });
            }
        }
    };

    /** @param {ts.Node} node */
    const visit = (node) => {
        if (ts.isJsxText(node)) {
            jsxTextRanges.push([node.pos, node.end]);
            return;
        }
        add(ts.getLeadingCommentRanges(text, node.pos));
        add(ts.getTrailingCommentRanges(text, node.end));
        for (const child of node.getChildren(sourceFile)) {
            visit(child);
        }
    };
    visit(sourceFile);

    return [...found.values()]
        .filter(
            (comment) =>
                !jsxTextRanges.some(
                    ([start, end]) => comment.pos >= start && comment.pos < end
                )
        )
        .sort((a, b) => a.pos - b.pos);
}

/**
 * The comment's text without its delimiters, or undefined when it isn't a
 * translators comment.
 *
 * @param {string} raw
 * @returns {string | undefined}
 */
function translatorsComment(raw) {
    const body = raw.startsWith('//')
        ? raw.slice(2)
        : raw
              .slice(2, -2)
              .split('\n')
              .map((line) => line.replace(/^\s*\*?/, ''))
              .join(' ');
    const normalized = body.replace(/\s+/g, ' ').trim();

    return /^translators:/i.test(normalized) ? normalized : undefined;
}

/**
 * @typedef {object} ExtractedCall
 * @property {string} msgid
 * @property {string} [msgctxt]
 * @property {string} [msgid_plural]
 * @property {string} [comment] Translators comment, without delimiters.
 * @property {number} line One-based line of the call.
 */

/**
 * Extract every package-domain i18n call from one file's source.
 *
 * @param {string} source
 * @param {string} [fileName]
 * @param {(message: string) => void} [warn] Called for a package-domain
 *     call whose strings aren't static.
 * @returns {ExtractedCall[]}
 */
export function extractFromSource(source, fileName = 'file.tsx', warn = () => {}) {
    const sourceFile = ts.createSourceFile(
        fileName,
        source,
        ts.ScriptTarget.Latest,
        true,
        fileName.endsWith('.tsx') ? ts.ScriptKind.TSX : ts.ScriptKind.TS
    );
    const lineOf = (/** @type {number} */ pos) =>
        sourceFile.getLineAndCharacterOfPosition(pos).line + 1;

    /** @type {Array<{ call: ts.CallExpression; fn: keyof typeof SIGNATURES }>} */
    const calls = [];
    /** @param {ts.Node} node */
    const visit = (node) => {
        if (ts.isCallExpression(node)) {
            const fn = calleeName(node);
            if (fn) {
                calls.push({ call: node, fn });
            }
        }
        ts.forEachChild(node, visit);
    };
    visit(sourceFile);

    // Every i18n call (any domain), so a comment is never attached past
    // another call that sits between it and this one.
    const callStarts = calls
        .map(({ call }) => call.getStart(sourceFile))
        .sort((a, b) => a - b);
    const comments = calls.length > 0 ? collectComments(sourceFile) : [];

    /** @type {ExtractedCall[]} */
    const extracted = [];

    for (const { call, fn } of calls) {
        const signature = SIGNATURES[fn];
        const args = call.arguments;
        if (!isPackageDomain(args[signature.domain])) {
            continue;
        }

        const start = call.getStart(sourceFile);
        const line = lineOf(start);
        const msgid = staticString(args[signature.msgid]);
        const msgctxt =
            'msgctxt' in signature
                ? staticString(args[signature.msgctxt])
                : undefined;
        const msgidPlural =
            'msgid_plural' in signature
                ? staticString(args[signature.msgid_plural])
                : undefined;

        const nonStatic = [
            msgid === undefined ? 'msgid' : undefined,
            'msgctxt' in signature && msgctxt === undefined ? 'context' : undefined,
            'msgid_plural' in signature && msgidPlural === undefined
                ? 'plural'
                : undefined,
        ].filter(Boolean);
        if (msgid === undefined || nonStatic.length > 0) {
            warn(
                `${fileName}:${line}: ${fn}() ${nonStatic.join(', ')} is not a static string; skipped.`
            );
            continue;
        }

        // Nearest comment ending before the call, on the call's line or the
        // line above, with no other i18n call in between.
        let comment;
        const previousCall = callStarts.filter((pos) => pos < start).pop() ?? -1;
        const nearest = comments.filter((c) => c.end <= start).pop();
        if (
            nearest &&
            nearest.pos > previousCall &&
            lineOf(nearest.end) >= line - 1
        ) {
            comment = translatorsComment(nearest.text);
        }

        extracted.push({
            msgid,
            msgctxt,
            msgid_plural: msgidPlural,
            comment,
            line,
        });
    }

    return extracted;
}

/**
 * @typedef {object} PotEntry
 * @property {string} msgid
 * @property {string} [msgctxt]
 * @property {string} [msgid_plural]
 * @property {Set<string>} comments
 * @property {Set<string>} references
 */

/** @param {{ msgid: string; msgctxt?: string }} entry */
export function keyFor(entry) {
    return `${entry.msgctxt ?? ''}\u0004${entry.msgid}`;
}

/**
 * Extract and merge every entry under `sourceDir`, keyed by
 * {@link keyFor}. References are `path:line` relative to `root`.
 *
 * @param {{ root?: string; sourceDir?: string; warn?: (message: string) => void }} [options]
 * @returns {Promise<Map<string, PotEntry>>}
 */
export async function extract({
    root = ROOT,
    sourceDir = SOURCE_DIR,
    warn = (message) => console.warn(`Warning: ${message}`),
} = {}) {
    /** @type {Map<string, PotEntry>} */
    const entries = new Map();
    const files = await collectSourceFiles(sourceDir);

    for (const file of files) {
        const source = await readFile(file, 'utf8');
        const rel = relative(root, file).split(sep).join('/');

        for (const found of extractFromSource(source, rel, warn)) {
            const key = keyFor(found);
            const reference = `${rel}:${found.line}`;
            const existing = entries.get(key);
            if (existing) {
                existing.references.add(reference);
                if (found.comment) {
                    existing.comments.add(found.comment);
                }
                // Singular-first / plural-later: promote the plural form
                // into the existing entry so `_n`/`_nx` callers don't
                // lose their plural data when a `__` call appeared first.
                // (msgctxt keys the entry, so it can't differ here.)
                if (
                    existing.msgid_plural === undefined &&
                    found.msgid_plural !== undefined
                ) {
                    existing.msgid_plural = found.msgid_plural;
                }
            } else {
                entries.set(key, {
                    msgid: found.msgid,
                    msgctxt: found.msgctxt,
                    msgid_plural: found.msgid_plural,
                    comments: new Set(found.comment ? [found.comment] : []),
                    references: new Set([reference]),
                });
            }
        }
    }

    return entries;
}

/** @param {string} value */
export function escapePotString(value) {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/\t/g, '\\t');
}

/** @param {Map<string, PotEntry>} entries */
export function renderPot(entries) {
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
            for (const comment of entry.comments) {
                lines.push(`#. ${comment}`);
            }
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

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    main().catch((error) => {
        console.error(error);
        process.exit(1);
    });
}
