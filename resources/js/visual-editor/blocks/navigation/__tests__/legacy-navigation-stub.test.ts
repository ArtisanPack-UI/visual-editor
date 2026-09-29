/**
 * Legacy `artisanpack/navigation` stub (#808 follow-up) — parse → validate →
 * migrate with the real `@wordpress/blocks` parser/serializer.
 *
 * Covers the two persisted 1.11 shapes:
 *
 * - the self-closing `ref` form, whose supports-derived attributes (align,
 *   anchor, typography, layout, animations, …) must survive parse against
 *   the stub schema AND the swap to `core/navigation`;
 * - the inline-children form, which must validate against the stub's save
 *   and keep its children when an un-migrated stub is re-serialized.
 *
 * `core/navigation` / `core/navigation-link` register from their real
 * upstream `block.json` metadata (the upstream `index.mjs` pulls the whole
 * edit surface, which cannot load under jsdom); their saves mirror upstream.
 *
 * @since 1.12.0
 */

import { beforeAll, describe, expect, it, vi } from 'vitest';
import { createElement } from 'react';

vi.mock('@wordpress/blocks', async () =>
    (await import('./wp-blocks-cjs')).requireCjs('@wordpress/blocks'),
);
vi.mock('@wordpress/hooks', async () =>
    (await import('./wp-blocks-cjs')).requireCjs('@wordpress/hooks'),
);
// The real block editor — its `blocks.registerBlockType` hooks add the
// supports-derived attributes (align, anchor, style, fontSize, layout, …)
// that parse must preserve.
vi.mock('@wordpress/block-editor', async () =>
    (await import('./wp-blocks-cjs')).requireCjs('@wordpress/block-editor'),
);

// `register-forked-cores` imports the upstream `init` entrypoints, which
// pull the whole navigation edit surface; only its filter is used here.
vi.mock('@wordpress/block-library/build-module/navigation/index.mjs', () => ({ init: () => undefined }));
vi.mock('@wordpress/block-library/build-module/navigation-link/index.mjs', () => ({ init: () => undefined }));
vi.mock('@wordpress/block-library/build-module/navigation-submenu/index.mjs', () => ({ init: () => undefined }));
vi.mock('@wordpress/block-library/build-module/page-list/index.mjs', () => ({ init: () => undefined }));
vi.mock('@wordpress/block-library/build-module/page-list-item/index.mjs', () => ({ init: () => undefined }));
vi.mock('@wordpress/block-library/build-module/home-link/index.mjs', () => ({ init: () => undefined }));
vi.mock('@wordpress/block-library/build-module/loginout/index.mjs', () => ({ init: () => undefined }));

import {
    createBlock,
    getBlockType,
    parse,
    registerBlockType,
    serialize,
} from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import { registerAnimationsAttribute } from '../../../animations/register-attribute';
import { registerForkedCoreAnimationsSupportFilter } from '../../../editor/register-forked-cores';
import stubMetadata from '../block.json';
import stubSave from '../save';
import { requireCjs } from './wp-blocks-cjs';

const coreNavigationMetadata = requireCjs(
    '@wordpress/block-library/build-module/navigation/block.json',
);
const coreNavigationLinkMetadata = requireCjs(
    '@wordpress/block-library/build-module/navigation-link/block.json',
);

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyBlock = any;

const innerContentSave = () => createElement(InnerBlocks.Content);

const ANIMATIONS = {
    entrance: { effect: 'fade-in', duration: 600 },
};

// Canonical serialization order: block.json attributes first, then the
// supports-derived ones in hook-registration order.
const REF_ATTRIBUTES = {
    ref: 42,
    overlayMenu: 'never',
    align: 'wide',
    anchor: 'site-nav',
    fontSize: 'small',
    style: { typography: { letterSpacing: '2px' } },
    layout: { type: 'flex', justifyContent: 'right' },
    artisanpackAnimations: ANIMATIONS,
};

const REF_MARKUP = `<!-- wp:artisanpack/navigation ${JSON.stringify(REF_ATTRIBUTES)} /-->`;

const INLINE_MARKUP = [
    '<!-- wp:artisanpack/navigation {"overlayMenu":"never"} -->',
    '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom","isTopLevelLink":true} /-->',
    '',
    '<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom","isTopLevelLink":true} /-->',
    '<!-- /wp:artisanpack/navigation -->',
].join('\n');

/**
 * Mirror of the stub edit's `buildReplacement()` — the migration swaps the
 * stub for `core/navigation` with its attributes and inner blocks intact.
 */
function migrate(block: AnyBlock): AnyBlock {
    return createBlock('core/navigation', { ...block.attributes }, block.innerBlocks);
}

describe('legacy artisanpack/navigation stub', () => {
    beforeAll(() => {
        // Boot order mirrors the editors: feature filters first, then blocks.
        registerAnimationsAttribute();
        registerForkedCoreAnimationsSupportFilter();

        registerBlockType(
            { ...coreNavigationMetadata } as AnyBlock,
            { edit: () => null, save: ({ attributes }: AnyBlock) => (attributes.ref ? null : innerContentSave()) } as AnyBlock,
        );
        registerBlockType(
            { ...coreNavigationLinkMetadata } as AnyBlock,
            { edit: () => null, save: innerContentSave } as AnyBlock,
        );
        registerBlockType(
            { ...stubMetadata } as AnyBlock,
            { edit: () => null, save: stubSave } as AnyBlock,
        );
    });

    it('declares every supports key the 1.11 fork declared, minus the inserter', () => {
        const supports = getBlockType('artisanpack/navigation')?.supports as Record<string, unknown>;

        expect(supports.inserter).toBe(false);
        for (const key of [
            'align',
            'anchor',
            'ariaLabel',
            'typography',
            'spacing',
            'layout',
            'artisanpackAnimations',
            'position',
            'contentRole',
            'interactivity',
        ]) {
            expect(supports, key).toHaveProperty(key);
        }
    });

    describe('self-closing ref form', () => {
        it('parses valid with every attribute preserved', () => {
            const [block] = parse(REF_MARKUP) as AnyBlock[];

            expect(block.name).toBe('artisanpack/navigation');
            expect(block.isValid).toBe(true);
            expect(block.attributes).toMatchObject(REF_ATTRIBUTES);
        });

        it('carries every attribute through the migration to core/navigation', () => {
            const [block] = parse(REF_MARKUP) as AnyBlock[];
            const migrated = migrate(block);

            expect(migrated.name).toBe('core/navigation');
            expect(getBlockType('core/navigation')?.supports?.artisanpackAnimations).toBe(true);
            expect(migrated.attributes).toMatchObject(REF_ATTRIBUTES);
            expect(serialize([migrated])).toBe(
                `<!-- wp:navigation ${JSON.stringify(REF_ATTRIBUTES)} /-->`,
            );
        });

        it('re-serializes an un-migrated stub byte-identically', () => {
            const blocks = parse(REF_MARKUP) as AnyBlock[];

            expect(serialize(blocks)).toBe(REF_MARKUP);
        });
    });

    describe('inline-children form', () => {
        it('parses valid with its navigation-link children', () => {
            const [block] = parse(INLINE_MARKUP) as AnyBlock[];

            expect(block.isValid).toBe(true);
            expect(block.innerBlocks.map((inner: AnyBlock) => inner.name)).toEqual([
                'core/navigation-link',
                'core/navigation-link',
            ]);
            expect(block.innerBlocks.every((inner: AnyBlock) => inner.isValid)).toBe(true);
        });

        it('keeps the children when an un-migrated stub is re-serialized', () => {
            const blocks = parse(INLINE_MARKUP) as AnyBlock[];
            const output = serialize(blocks);

            expect(output).toContain('"label":"Home"');
            expect(output).toContain('"label":"About"');

            const [reparsed] = parse(output) as AnyBlock[];
            expect(reparsed.isValid).toBe(true);
            expect(reparsed.innerBlocks).toHaveLength(2);
        });

        it('keeps the children through the migration to core/navigation', () => {
            const [block] = parse(INLINE_MARKUP) as AnyBlock[];
            const migrated = migrate(block);

            expect(migrated.innerBlocks.map((inner: AnyBlock) => inner.attributes.label)).toEqual([
                'Home',
                'About',
            ]);
            expect(serialize([migrated])).toContain('<!-- wp:navigation-link {"label":"About"');
        });
    });
});
