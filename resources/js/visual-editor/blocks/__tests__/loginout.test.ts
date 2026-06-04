/**
 * Loginout fork (#522) — block.json + save + transforms contract.
 *
 * Single dynamic auth-state block. Covers, in one suite: namespace +
 * textdomain + theme category on block.json, the dynamic save shape
 * (`null`), the bidirectional `core/* ↔ artisanpack/*` rollout
 * transforms, and the attribute schema (the two display toggles
 * upstream carries: displayLoginAsForm + redirectToCurrent). Mirrors
 * the i5/i6 + query-family suites.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: innerBlocks ?? [],
    }),
}));

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: Object.assign(
        () => null,
        { Content: () => null }
    ),
    useBlockProps: Object.assign(
        () => ({}),
        { save: () => ({}) }
    ),
}));

import loginoutMeta from '../loginout/block.json';
import loginoutTransforms from '../loginout/transforms';
import loginoutSave from '../loginout/save';

interface BlockTransform {
    type: string;
    blocks: string[];
    transform: (
        attrs: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => {
        name: string;
        attributes: Record<string, unknown>;
        innerBlocks: unknown[];
    };
}
interface TransformsModule {
    from: BlockTransform[];
    to: BlockTransform[];
}

describe('loginout block.json', () => {
    it('declares the artisanpack namespace + textdomain + theme category', () => {
        expect(loginoutMeta.name).toBe('artisanpack/loginout');
        expect(loginoutMeta.textdomain).toBe('artisanpack-visual-editor');
        expect(loginoutMeta.category).toBe('theme');
    });

    it('carries the two display toggles upstream ships', () => {
        const attrs = loginoutMeta.attributes as Record<
            string,
            { type: string; default?: unknown }
        >;
        expect(attrs.displayLoginAsForm).toBeDefined();
        expect(attrs.displayLoginAsForm.type).toBe('boolean');
        expect(attrs.displayLoginAsForm.default).toBe(false);

        expect(attrs.redirectToCurrent).toBeDefined();
        expect(attrs.redirectToCurrent.type).toBe('boolean');
        expect(attrs.redirectToCurrent.default).toBe(true);
    });

    it('declares the loginout keywords upstream ships', () => {
        expect(loginoutMeta.keywords).toEqual(
            expect.arrayContaining(['login', 'logout', 'form'])
        );
    });

    it('is a standalone block (no ancestor or parent lock)', () => {
        const meta = loginoutMeta as {
            ancestor?: string[];
            parent?: string[];
        };
        // Loginout is placed wherever a theme wants the auth link
        // (header / sidebar / footer). Locking it would prevent the
        // most common usages.
        expect(meta.ancestor).toBeUndefined();
        expect(meta.parent).toBeUndefined();
    });
});

describe('loginout save', () => {
    it('returns null (dynamic block, server-rendered)', () => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const result = (loginoutSave as () => any)();

        expect(result).toBeNull();
    });
});

describe('loginout transforms', () => {
    it('ships bidirectional core/loginout ↔ artisanpack/loginout transforms', () => {
        const t = loginoutTransforms as TransformsModule;
        const from = t.from.find((e) => e.blocks?.includes('core/loginout'));
        const to = t.to.find((e) => e.blocks?.includes('core/loginout'));

        expect(from).toBeDefined();
        expect(to).toBeDefined();

        expect(from!.transform({ displayLoginAsForm: true })).toMatchObject({
            name: 'artisanpack/loginout',
            attributes: { displayLoginAsForm: true },
        });
        expect(to!.transform({ redirectToCurrent: false })).toMatchObject({
            name: 'core/loginout',
            attributes: { redirectToCurrent: false },
        });
    });

    it('preserves both display toggles in both directions', () => {
        // Single-attribute cases (above) prove each direction wires up,
        // but a host document is more likely to carry both attributes —
        // a future regression that drops one on round-trip would silently
        // reset the toggle in the editor. Cover the full-attributes path
        // explicitly on both directions.
        const t = loginoutTransforms as TransformsModule;
        const from = t.from.find((e) => e.blocks?.includes('core/loginout'));
        const to = t.to.find((e) => e.blocks?.includes('core/loginout'));

        const bothAttrs = { displayLoginAsForm: true, redirectToCurrent: false };

        expect(from!.transform(bothAttrs)).toMatchObject({
            name: 'artisanpack/loginout',
            attributes: bothAttrs,
        });
        expect(to!.transform(bothAttrs)).toMatchObject({
            name: 'core/loginout',
            attributes: bothAttrs,
        });
    });
});
