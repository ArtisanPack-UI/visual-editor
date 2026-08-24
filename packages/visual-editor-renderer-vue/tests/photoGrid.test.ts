/**
 * Photo Grid wrapper scope tests (#714).
 *
 * The `has-photo-grid` + `photo-grid-<hash>` scope class must match the
 * Blade `PhotoGridSupport::wrapperForBlock()` byte-for-byte, so the expected
 * class names below are pinned to `photo-grid-` + the first 12 hex chars of
 * `sha1( <declaration> )`.
 */

import { describe, expect, it } from 'vitest';
import { photoGridScope, stampPhotoGridScopes } from '../src/support/photoGrid';
import type { Block } from '../src/types';

function group(attributes: Record<string, unknown>, innerBlocks: Block[] = []): Block {
    return { name: 'core/group', attributes, innerBlocks } as unknown as Block;
}

describe('photoGridScope', () => {
    it('emits the has-photo-grid + hashed scope class and rule like Blade', () => {
        const scope = photoGridScope({
            photoGrid: {
                enabled: true,
                aspectRatio: '16/9',
                objectFit: 'cover',
                objectPosition: '50% 50%',
            },
        });

        expect(scope?.className).toBe('has-photo-grid photo-grid-6873a2dd85dc');
        expect(scope?.css).toBe(
            '.photo-grid-6873a2dd85dc{--ap-photo-grid-fit:cover;--ap-photo-grid-position:50% 50%;--ap-photo-grid-aspect:16/9;}'
        );
    });

    it('omits the aspect declaration when the ratio is inherit/invalid', () => {
        const scope = photoGridScope({
            photoGrid: { enabled: true, aspectRatio: null, objectFit: 'contain', objectPosition: 'top left' },
        });

        expect(scope?.css).toBe(
            '.' +
                scope!.className.split(' ')[1] +
                '{--ap-photo-grid-fit:contain;--ap-photo-grid-position:top left;}'
        );
        expect(scope?.css).not.toContain('--ap-photo-grid-aspect');
    });

    it('returns null when the feature is off or absent', () => {
        expect(photoGridScope({})).toBeNull();
        expect(photoGridScope({ photoGrid: null })).toBeNull();
        expect(photoGridScope({ photoGrid: { enabled: false } })).toBeNull();
        // A truthy-but-not-true `enabled` must not switch the feature on.
        expect(photoGridScope({ photoGrid: { enabled: 1 } })).toBeNull();
    });

    it('falls back an invalid object-fit to cover and a hostile position to the default', () => {
        const scope = photoGridScope({
            photoGrid: {
                enabled: true,
                objectFit: 'weird',
                // Would close the declaration and inject a sibling rule.
                objectPosition: '50%}html{opacity:0',
            },
        });

        expect(scope?.css).toContain('--ap-photo-grid-fit:cover');
        expect(scope?.css).toContain('--ap-photo-grid-position:50% 50%');
        expect(scope?.css).not.toContain('opacity');
        expect(scope?.css).not.toContain('}html{');
    });

    it('rejects a non-ASCII whitespace object-position (PCRE `\\s` parity)', () => {
        // A U+00A0 no-break space matches JS `\s` but not PHP's PCRE `\s`,
        // so the allowlist must reject it to keep the scope hash aligned
        // with Blade, which drops the value to the default.
        const scope = photoGridScope({
            photoGrid: { enabled: true, objectPosition: 'top\u00A0left' },
        });

        expect(scope?.css).toContain('--ap-photo-grid-position:50% 50%;');
        expect(scope?.css).not.toContain('top');
    });

    it('mirrors PHP trim (not String.trim) for exotic leading/trailing whitespace', () => {
        // Form feed is NOT stripped by PHP trim, so the aspect stays
        // invalid and no `--ap-photo-grid-aspect` is emitted. String.trim
        // would strip it and wrongly emit the declaration (→ a scope hash
        // Blade never produces).
        const ff = photoGridScope({
            photoGrid: { enabled: true, aspectRatio: '\f4/3', objectFit: 'cover', objectPosition: '50% 50%' },
        });
        expect(ff?.css).not.toContain('--ap-photo-grid-aspect');

        // A leading NBSP is NOT stripped by PHP trim, so the allowlist
        // rejects the value and it falls back to the default. String.trim
        // would strip it and wrongly keep `top`.
        const nbsp = photoGridScope({ photoGrid: { enabled: true, objectPosition: '\u00A0top' } });
        expect(nbsp?.css).toContain('--ap-photo-grid-position:50% 50%;');
        expect(nbsp?.css).not.toContain('top');

        // A normal ASCII space IS stripped by both, so a padded valid ratio
        // still validates.
        const padded = photoGridScope({
            photoGrid: { enabled: true, aspectRatio: ' 4/3 ', objectFit: 'cover', objectPosition: '50% 50%' },
        });
        expect(padded?.css).toContain('--ap-photo-grid-aspect:4/3;');
    });

    it('rejects a non-positive or malformed aspect ratio', () => {
        const zero = photoGridScope({ photoGrid: { enabled: true, aspectRatio: '0/1' } });
        expect(zero?.css).not.toContain('--ap-photo-grid-aspect');

        const malformed = photoGridScope({ photoGrid: { enabled: true, aspectRatio: '16:9' } });
        expect(malformed?.css).not.toContain('--ap-photo-grid-aspect');
    });
});

describe('stampPhotoGridScopes', () => {
    it('stamps the scope onto matching blocks and accumulates unique CSS', () => {
        const attrs = {
            photoGrid: { enabled: true, aspectRatio: '16/9', objectFit: 'cover', objectPosition: '50% 50%' },
        };
        const tree: Block[] = [group(attrs), group(attrs), group({})];

        const { tree: stamped, css } = stampPhotoGridScopes(tree);

        expect((stamped[0].attributes as Record<string, unknown>)._vePhotoGridScope).toBe(
            'has-photo-grid photo-grid-6873a2dd85dc'
        );
        expect((stamped[1].attributes as Record<string, unknown>)._vePhotoGridScope).toBe(
            'has-photo-grid photo-grid-6873a2dd85dc'
        );
        expect((stamped[2].attributes as Record<string, unknown>)._vePhotoGridScope).toBeUndefined();

        // Identical declarations share one scope, so the rule emits once.
        const occurrences = css.split('.photo-grid-6873a2dd85dc{').length - 1;
        expect(occurrences).toBe(1);
    });

    it('stamps nested columns and grid blocks too', () => {
        const on = { photoGrid: { enabled: true, objectFit: 'cover', objectPosition: '50% 50%' } };
        const tree: Block[] = [
            {
                name: 'core/columns',
                attributes: on,
                innerBlocks: [{ name: 'artisanpack/grid', attributes: on, innerBlocks: [] } as unknown as Block],
            } as unknown as Block,
        ];

        const { tree: stamped } = stampPhotoGridScopes(tree);
        const columns = stamped[0].attributes as Record<string, unknown>;
        const grid = ((stamped[0].innerBlocks ?? []) as Block[])[0].attributes as Record<string, unknown>;

        expect(columns._vePhotoGridScope).toContain('has-photo-grid photo-grid-');
        expect(grid._vePhotoGridScope).toContain('has-photo-grid photo-grid-');
    });

    it('strips an author-supplied _vePhotoGridScope side-channel value', () => {
        const injected = group({ _vePhotoGridScope: 'fixed inset-0 z-50 bg-black' });
        const withFeature = group({
            photoGrid: { enabled: true, objectFit: 'cover', objectPosition: '50% 50%' },
            _vePhotoGridScope: 'attacker-class',
        });

        const { tree: stamped } = stampPhotoGridScopes([injected, withFeature]);

        // No scope resolves → the injected value is removed entirely.
        expect((stamped[0].attributes as Record<string, unknown>)._vePhotoGridScope).toBeUndefined();
        // A real scope resolves → it overwrites the crafted value.
        expect((stamped[1].attributes as Record<string, unknown>)._vePhotoGridScope).toContain(
            'has-photo-grid photo-grid-'
        );
        expect((stamped[1].attributes as Record<string, unknown>)._vePhotoGridScope).not.toContain('attacker-class');
    });

    it('leaves non-panel blocks untouched even with a photoGrid attribute', () => {
        // A paragraph is not one of the three panel-bearing blocks, so a
        // stray photoGrid attribute must not stamp a scope.
        const para = {
            name: 'core/paragraph',
            attributes: { photoGrid: { enabled: true, objectFit: 'cover', objectPosition: '50% 50%' } },
            innerBlocks: [],
        } as unknown as Block;

        const { tree: stamped, css } = stampPhotoGridScopes([para]);

        expect((stamped[0].attributes as Record<string, unknown>)._vePhotoGridScope).toBeUndefined();
        expect(css).toBe('');
    });
});
