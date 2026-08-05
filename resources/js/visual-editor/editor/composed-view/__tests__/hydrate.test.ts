/**
 * Hydration of server-sourced applied-template blocks (#621), plus the
 * bindings pass-through that #622's guarantee now rests on.
 *
 * The endpoint returns plain JSON — no `clientId`, no `isValid`, no schema
 * defaults. Handing that to the editor trips Gutenberg's
 * serialize→parse→compare validation on every block ("this block contains
 * unexpected or invalid content"), so `hydrateBlocks` runs each one through
 * `createBlock`.
 *
 * `@wordpress/blocks` is mocked, per the convention in the other block
 * tests here — importing it for real fails under vitest on a JSON import
 * attribute. That scopes these tests to *our* contract: what hydration
 * hands `createBlock`, how it normalizes the PHP payload, and how it
 * behaves when `createBlock` rejects a name. Whether `createBlock` then
 * keeps an attribute is Gutenberg's schema sanitization, driven by
 * `registerBindingsAttribute` declaring `bindings` on every block — see
 * `bindings/__tests__/register-attribute.test.ts`.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

let clientIdCounter = 0;

const createBlock = vi.fn(
    (
        name: string,
        attributes: Record<string, unknown>,
        innerBlocks: BlockInstance[]
    ) => {
        clientIdCounter += 1;

        return {
            clientId: `cid-${clientIdCounter}`,
            name,
            attributes,
            innerBlocks,
            isValid: true,
        };
    }
);

vi.mock('@wordpress/blocks', () => ({
    createBlock: (...args: unknown[]) =>
        (createBlock as unknown as (...a: unknown[]) => unknown)(...args),
}));

import { hydrateAppliedTemplate, hydrateBlocks } from '../hydrate';

function raw(
    name: string,
    attributes: unknown = {},
    innerBlocks: unknown[] = []
): BlockInstance {
    return { name, attributes, innerBlocks } as unknown as BlockInstance;
}

beforeEach(() => {
    createBlock.mockClear();
    clientIdCounter = 0;
});

describe('hydrateBlocks', () => {
    it('rebuilds each block through createBlock', () => {
        const hydrated = hydrateBlocks([
            raw('artisanpack/heading', { level: 1 }),
        ]);

        expect(createBlock).toHaveBeenCalledWith(
            'artisanpack/heading',
            { level: 1 },
            []
        );
        expect(hydrated[0].clientId).toBe('cid-1');
        expect(hydrated[0].isValid).toBe(true);
    });

    it('passes a bindings map through untouched (#622)', () => {
        // Chrome blocks carry their bindings into the preview; hydration
        // must not strip or reshape the map on the way.
        const bindings = {
            content: { source: 'cms/entity-field', args: { key: 'title' } },
        };

        hydrateBlocks([
            raw('artisanpack/post-title', { content: 'Sample', bindings }),
        ]);

        expect(createBlock).toHaveBeenCalledWith(
            'artisanpack/post-title',
            { content: 'Sample', bindings },
            []
        );
    });

    it('coerces PHP empty-array attributes to an object', () => {
        // `json_encode([])` emits `[]`, not `{}` — an array trips
        // createBlock's schema merger.
        hydrateBlocks([raw('artisanpack/separator', [])]);

        expect(createBlock).toHaveBeenCalledWith(
            'artisanpack/separator',
            {},
            []
        );
    });

    it('coerces null and non-object attributes to an object', () => {
        hydrateBlocks([raw('a/one', null), raw('a/two', 'nope')]);

        expect(createBlock).toHaveBeenNthCalledWith(1, 'a/one', {}, []);
        expect(createBlock).toHaveBeenNthCalledWith(2, 'a/two', {}, []);
    });

    it('hydrates nested innerBlocks depth-first', () => {
        const hydrated = hydrateBlocks([
            raw('artisanpack/group', {}, [
                raw('artisanpack/paragraph', { content: 'inner' }),
            ]),
        ]);

        expect(hydrated[0].innerBlocks).toHaveLength(1);
        expect(hydrated[0].innerBlocks[0].attributes.content).toBe('inner');
        // Inner block is built before its parent.
        expect(createBlock.mock.calls[0][0]).toBe('artisanpack/paragraph');
        expect(createBlock.mock.calls[1][0]).toBe('artisanpack/group');
    });

    it('gives every block a distinct clientId', () => {
        const hydrated = hydrateBlocks([
            raw('artisanpack/separator'),
            raw('artisanpack/separator'),
        ]);

        expect(hydrated[0].clientId).not.toBe(hydrated[1].clientId);
    });

    it('falls back to a valid instance when createBlock rejects the type', () => {
        createBlock.mockImplementationOnce(() => {
            throw new Error('unknown block type');
        });

        const hydrated = hydrateBlocks([raw('acme/never-registered', { a: 1 })]);

        expect(hydrated).toHaveLength(1);
        expect(hydrated[0].name).toBe('acme/never-registered');
        expect(hydrated[0].clientId).toBeTruthy();
        // `isValid` matters: without it the block renders the "unexpected
        // or invalid content" recovery UI inside the chrome preview.
        expect(hydrated[0].isValid).toBe(true);
        expect(hydrated[0].attributes).toEqual({ a: 1 });
    });

    it('returns an empty list unchanged', () => {
        expect(hydrateBlocks([])).toEqual([]);
        expect(createBlock).not.toHaveBeenCalled();
    });

    it('treats an absent innerBlocks as empty rather than throwing', () => {
        // PHP omitting `innerBlocks` is a natural serialization, and the
        // throw would land inside a useMemo with no boundary above it.
        const hydrated = hydrateBlocks([
            { name: 'artisanpack/group', attributes: {} } as unknown as
                BlockInstance,
        ]);

        expect(hydrated).toHaveLength(1);
        expect(hydrated[0].innerBlocks).toEqual([]);
    });

    it('skips malformed elements instead of dereferencing them', () => {
        const hydrated = hydrateBlocks([
            null as unknown as BlockInstance,
            { attributes: {} } as unknown as BlockInstance,
            raw('artisanpack/paragraph'),
        ]);

        expect(hydrated).toHaveLength(1);
        expect(hydrated[0].name).toBe('artisanpack/paragraph');
    });
});

describe('hydrateAppliedTemplate', () => {
    it('hydrates both the template blocks and its template parts', () => {
        const hydrated = hydrateAppliedTemplate({
            status: 'ok',
            slug: 'single-post',
            name: 'Single Post',
            source: 'theme',
            blocks: [raw('artisanpack/group')],
            template_parts: {
                header: {
                    slug: 'header',
                    area: 'header',
                    title: 'Header',
                    source: 'theme',
                    blocks: [raw('artisanpack/site-title')],
                },
            },
        });

        expect(hydrated.blocks[0].clientId).toBeTruthy();
        expect(hydrated.template_parts.header.blocks[0].clientId).toBeTruthy();

        // Envelope metadata rides through untouched.
        expect(hydrated.slug).toBe('single-post');
        expect(hydrated.template_parts.header.area).toBe('header');
    });

    it('tolerates malformed parts, since it is exported package API', () => {
        // The response validator rejects these before they reach the
        // editor, but this function is exported from the package index and
        // can be handed a payload that never went through it.
        const hydrated = hydrateAppliedTemplate({
            status: 'ok',
            slug: 'single-post',
            name: 'Single Post',
            source: 'theme',
            blocks: [],
            template_parts: {
                broken: null,
                blockless: { slug: 'blockless', area: 'header' },
                ok: {
                    slug: 'ok',
                    area: 'footer',
                    title: 'OK',
                    source: 'theme',
                    blocks: [raw('artisanpack/site-title')],
                },
            },
        } as unknown as Parameters<typeof hydrateAppliedTemplate>[0]);

        expect(hydrated.template_parts.broken).toBeUndefined();
        expect(hydrated.template_parts.blockless.blocks).toEqual([]);
        expect(hydrated.template_parts.ok.blocks[0].clientId).toBeTruthy();
    });
});
