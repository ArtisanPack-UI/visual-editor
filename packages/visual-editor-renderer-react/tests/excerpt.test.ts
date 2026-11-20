import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { trimExcerpt } from '../src/support/excerpt';
import { trimExcerpt as vueTrimExcerpt } from '../../visual-editor-renderer-vue/src/support/excerpt';
import { trimExcerpt as editorTrimExcerpt } from '../../../resources/js/visual-editor/blocks/post-excerpt/trim-excerpt';

const sharedCases = JSON.parse(
    readFileSync(
        resolve(dirname(fileURLToPath(import.meta.url)), '../../renderer-markup-parity/excerpt-trim-cases.json'),
        'utf8'
    )
).cases as Array<[string, unknown, string]>;

describe.each([
    ['react', trimExcerpt],
    ['vue', vueTrimExcerpt],
    ['editor', editorTrimExcerpt],
])('%s trimExcerpt matches PHP ExcerptTrimmer::trim() (#1139)', (_where, trim) => {
    it.each(sharedCases)('%j at %j → %j', (html, length, expected) => {
        expect(trim(html, length)).toBe(expected);
    });

    it('is a no-op on an excerpt it already trimmed', () => {
        const once = trim('word '.repeat(80), 20);
        expect(trim(once, 20)).toBe(once);
    });
});
