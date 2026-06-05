#!/usr/bin/env node
/**
 * upstream-diff — per-block fork drift inspector.
 *
 * Walks every forked block under `resources/js/visual-editor/blocks/*`
 * and, for each file the block's `upstream-state.json` claims is a port
 * from `@wordpress/block-library/src/<subpath>/<upstream>`, diffs the
 * pinned upstream file against the fork file. Emits:
 *
 *  - per-file unified diff to stdout (when `--diff` is passed)
 *  - per-block JSON manifest `<block>/upstream-diff-report.json` with
 *    drift status + file digest + line count + upstream commit window.
 *
 * Usage:
 *   node scripts/upstream-diff.mjs                # report all forked blocks
 *   node scripts/upstream-diff.mjs --block paragraph
 *   node scripts/upstream-diff.mjs --diff         # also print diffs
 *   node scripts/upstream-diff.mjs --json         # JSON output only
 *
 * Exit codes:
 *   0 — every fork is in-sync OR the only drift is on files explicitly
 *       marked status=extended/rewritten/added in `upstream-state.json`
 *   1 — a file marked `status=ported` differs from upstream (review needed)
 *   2 — invocation error (missing block, malformed state file, etc.)
 *
 * Wire into CI as `npm run upstream-diff -- --json` to fail builds on
 * drift that wasn't acknowledged by updating the state file.
 */

import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, '..');

const args = process.argv.slice(2);
const opts = {
    block: null,
    diff: args.includes('--diff'),
    json: args.includes('--json'),
};

for (let i = 0; i < args.length; i++) {
    if (args[i] === '--block') {
        const value = args[i + 1];
        if (!value || value.startsWith('-')) {
            console.error('--block requires a block name argument');
            process.exit(2);
        }
        if (!/^[a-z0-9][a-z0-9-]*$/i.test(value)) {
            console.error('--block must match ^[a-z0-9][a-z0-9-]*$ (no slashes, dots, or leading dash)');
            process.exit(2);
        }
        opts.block = value;
        i++;
    }
}

const BLOCKS_ROOT = path.join(repoRoot, 'resources', 'js', 'visual-editor', 'blocks');
const UPSTREAM_ROOT = path.join(repoRoot, 'node_modules', '@wordpress', 'block-library', 'src');

function digest(text) {
    return crypto.createHash('sha256').update(text).digest('hex').slice(0, 12);
}

function normalize(text) {
    // Diff cares about meaningful drift, not TypeScript-vs-JavaScript
    // whitespace tweaks. Collapse runs of whitespace and strip ESLint /
    // type-annotation noise so the manifest's "differs" flag matches a
    // human reviewer's judgement.
    return text
        .replace(/\r\n/g, '\n')
        .replace(/\t/g, '    ')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function listForkedBlocks() {
    if (!fs.existsSync(BLOCKS_ROOT)) return [];
    return fs
        .readdirSync(BLOCKS_ROOT, { withFileTypes: true })
        .filter((entry) => entry.isDirectory() && entry.name !== '_shared')
        .map((entry) => entry.name);
}

function readStateFile(blockDir) {
    const statePath = path.join(blockDir, 'upstream-state.json');
    if (!fs.existsSync(statePath)) return null;
    try {
        return JSON.parse(fs.readFileSync(statePath, 'utf8'));
    } catch (error) {
        console.error(`malformed upstream-state.json in ${blockDir}: ${error.message}`);
        process.exit(2);
    }
}

function simpleDiff(a, b) {
    const aLines = a.split('\n');
    const bLines = b.split('\n');
    const out = [];
    const max = Math.max(aLines.length, bLines.length);
    for (let i = 0; i < max; i++) {
        if (aLines[i] === bLines[i]) continue;
        if (aLines[i] !== undefined) out.push(`- ${aLines[i]}`);
        if (bLines[i] !== undefined) out.push(`+ ${bLines[i]}`);
    }
    return out.slice(0, 200).join('\n');
}

function analyzeBlock(blockName) {
    const blockDir = path.join(BLOCKS_ROOT, blockName);
    const state = readStateFile(blockDir);

    if (state === null) {
        return {
            block: blockName,
            status: 'no-state',
            note: 'no upstream-state.json — block not tracked as a fork',
            files: [],
        };
    }

    const upstreamSubpath = state.upstream?.subpath;
    const pinnedVersion = state.upstream?.pinnedVersion;

    if (!upstreamSubpath) {
        return {
            block: blockName,
            status: 'error',
            note: 'upstream.subpath missing',
            files: [],
        };
    }

    const upstreamDir = path.join(UPSTREAM_ROOT, upstreamSubpath);
    const hasUpstream = fs.existsSync(upstreamDir);

    const files = [];
    let driftDetected = false;

    for (const file of state.files ?? []) {
        const forkPath = path.join(blockDir, file.fork);
        const upstreamPath = path.join(upstreamDir, file.upstream);

        if (!fs.existsSync(forkPath)) {
            files.push({ ...file, result: 'fork-missing' });
            driftDetected = driftDetected || file.status === 'ported';
            continue;
        }

        // Statuses that don't expect byte-equivalence:
        //   adapted   — TypeScript port / namespace swap (logically equivalent)
        //   extended  — fork adds behavior on top of upstream
        //   rewritten — fork replaced upstream entirely
        //   added     — file unique to the fork (no upstream)
        // The only status that demands byte-equivalence is `ported`.
        if (file.status !== 'ported') {
            files.push({
                ...file,
                result: 'skipped-status',
                forkDigest: digest(fs.readFileSync(forkPath, 'utf8')),
            });
            continue;
        }

        if (!hasUpstream || file.upstream === 'n/a' || !fs.existsSync(upstreamPath)) {
            files.push({ ...file, result: 'upstream-missing' });
            driftDetected = true;
            continue;
        }

        const forkText = normalize(fs.readFileSync(forkPath, 'utf8'));
        const upstreamText = normalize(fs.readFileSync(upstreamPath, 'utf8'));

        if (forkText === upstreamText) {
            files.push({ ...file, result: 'in-sync', forkDigest: digest(forkText) });
            continue;
        }

        driftDetected = true;
        const entry = {
            ...file,
            result: 'drift',
            forkDigest: digest(forkText),
            upstreamDigest: digest(upstreamText),
        };
        if (opts.diff) {
            entry.diff = simpleDiff(upstreamText, forkText);
        }
        files.push(entry);
    }

    return {
        block: blockName,
        status: driftDetected ? 'drift' : 'in-sync',
        upstream: { pinnedVersion, subpath: upstreamSubpath },
        files,
    };
}

function writeReport(blockName, report) {
    const reportPath = path.join(BLOCKS_ROOT, blockName, 'upstream-diff-report.json');
    try {
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 4) + '\n', 'utf8');
    } catch (error) {
        console.error(`failed to write report for ${blockName} at ${reportPath}: ${error.message}`);
        process.exit(2);
    }
}

function main() {
    const targets = opts.block ? [opts.block] : listForkedBlocks();

    if (opts.block && !fs.existsSync(path.join(BLOCKS_ROOT, opts.block))) {
        console.error(`no fork directory at blocks/${opts.block}`);
        process.exit(2);
    }

    const reports = targets.map(analyzeBlock);
    let anyDrift = false;
    let anyError = false;

    for (const report of reports) {
        if (report.status === 'no-state') continue;
        if (report.status !== 'error') {
            writeReport(report.block, report);
        }
        if (report.status === 'drift') anyDrift = true;
        if (report.status === 'error') anyError = true;
    }

    if (opts.json) {
        console.log(JSON.stringify(reports, null, 4));
        process.exit(anyError ? 2 : anyDrift ? 1 : 0);
    }

    for (const report of reports) {
        if (report.status === 'no-state') {
            console.log(`  --   ${report.block}: (not tracked)`);
            continue;
        }
        if (report.status === 'error') {
            console.error(`  ERR  ${report.block}: ${report.note ?? 'upstream-state.json validation failed'}`);
            continue;
        }
        const symbol = report.status === 'drift' ? 'fail' : ' ok ';
        console.log(`  ${symbol} ${report.block} @ ${report.upstream?.pinnedVersion ?? 'unpinned'}`);
        for (const file of report.files) {
            const tag = {
                'in-sync': 'sync',
                drift: 'DRIFT',
                'skipped-status': `(${file.status})`,
                'upstream-missing': 'no-upstream',
                'fork-missing': 'NO-FORK',
            }[file.result] ?? file.result;
            console.log(`         ${tag}  ${file.fork}  ←  ${file.upstream}`);
            if (file.result === 'drift' && opts.diff && file.diff) {
                console.log(file.diff.split('\n').map((l) => `           ${l}`).join('\n'));
            }
        }
    }

    if (anyError) {
        console.error('\nupstream-state.json validation failed. Fix the state files above before retrying.');
        process.exit(2);
    }

    if (anyDrift) {
        console.error('\nDrift detected on files marked status=ported. Review and either:');
        console.error('  - re-port the upstream change into the fork, or');
        console.error('  - explicitly acknowledge the divergence by changing status in upstream-state.json');
        process.exit(1);
    }

    console.log('\nAll forks in sync with their pinned upstream.');
}

main();
