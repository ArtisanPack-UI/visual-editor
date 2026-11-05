/**
 * Image and Gallery lightbox (#1046).
 */

import { mount, flushPromises } from '@vue/test-utils';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { setLightboxLabels } from '../src/index';
import { imageHasLightbox, stampLightboxDefault } from '../src/support/lightbox';
import { makeBlock } from './helpers';
import type { Block } from '../src/types';

const { bindLightbox } = vi.hoisted(() => ({ bindLightbox: vi.fn() }));

vi.mock('../src/support/lightboxOverlay.generated.js', () => ({ bindLightbox }));

const URL = 'https://example.test/a.jpg';
const image = (attributes: Record<string, unknown>, name = 'artisanpack/image'): Block[] =>
    [makeBlock(name, { url: URL, alt: 'A cat', ...attributes })] as Block[];

const ssr = (tree: Block[], props: Record<string, unknown> = {}): Promise<string> =>
    renderToString(createSSRApp({ render: () => h(BlockTree, { tree, ...props }) }));

async function settle(): Promise<void> {
    await flushPromises();
    await vi.dynamicImportSettled();
    await flushPromises();
}

describe('imageHasLightbox', () => {
    it.each([
        ['off by default', { url: URL }, false],
        ['on for the block', { url: URL, lightbox: { enabled: true } }, true],
        ['on from the theme', { url: URL, _veLightbox: true }, true],
        ['the block over the theme', { url: URL, lightbox: { enabled: false }, _veLightbox: true }, false],
        ['a link over the block', { url: URL, href: '/x', lightbox: { enabled: true } }, false],
        ['nothing to enlarge', { lightbox: { enabled: true } }, false],
    ])('%s', (_label, attributes, expected) => {
        expect(imageHasLightbox(attributes)).toBe(expected);
    });
});

describe('stampLightboxDefault', () => {
    it('reaches nested images', () => {
        const tree = [makeBlock('core/gallery', {}, [makeBlock('core/image', {})])] as Block[];

        expect(stampLightboxDefault(tree, true)[0].innerBlocks?.[0].attributes?._veLightbox).toBe(true);
    });

    it('returns the same tree when there is nothing to change', () => {
        const plain = [makeBlock('core/image', {})] as Block[];
        const stamped = [makeBlock('core/image', { _veLightbox: true })] as Block[];

        expect(stampLightboxDefault(plain, false)).toBe(plain);
        expect(stampLightboxDefault(stamped, true)).toBe(stamped);
    });

    it('drops a saved _veLightbox when the default is off', () => {
        const tree = [makeBlock('core/group', {}, [makeBlock('core/image', { _veLightbox: true })])] as Block[];

        expect(stampLightboxDefault(tree, false)[0].innerBlocks?.[0].attributes).not.toHaveProperty('_veLightbox');
    });
});

describe('ImageBlock', () => {
    it('renders an existing image unchanged', async () => {
        expect(await ssr(image({}))).not.toContain('lightbox');
    });

    it('prints the container, labels and a hidden button trigger', async () => {
        const html = await ssr(image({ lightbox: { enabled: true } }));

        expect(html).toContain('class="wp-block-image wp-lightbox-container"');
        expect(html).toContain('data-ap-lightbox-labels=');
        expect(html).toMatch(/<button type="button" class="lightbox-trigger" aria-haspopup="dialog" aria-label="Enlarge image: A cat" style="display:none/);
    });

    it.each([['core/image'], ['artisanpack/image']])('applies the theme default to %s', async (name) => {
        expect(await ssr(image({}, name), { lightboxDefault: true })).toContain('wp-lightbox-container');
    });

    it('ignores a saved _veLightbox without a theme default, as Blade does', async () => {
        const html = await ssr([makeBlock('core/image', { url: URL, _veLightbox: true })] as Block[]);

        expect(html).not.toContain('wp-lightbox-container');
        expect(html).not.toContain('lightbox-trigger');
    });

    it('lets a block turn off the theme default', async () => {
        const html = await ssr(image({ lightbox: { enabled: false } }), { lightboxDefault: true });

        expect(html).not.toContain('wp-lightbox-container');
    });

    it('keeps the link and prints no lightbox on a linked image', async () => {
        const html = await ssr(image({ href: 'https://example.test/page', lightbox: { enabled: true } }));

        expect(html).toContain('<a href="https://example.test/page">');
        expect(html).not.toContain('lightbox');
    });

    it('binds the overlay in the browser, only when needed', async () => {
        bindLightbox.mockClear();

        const wrapper = mount(BlockTree, { props: { tree: image({ lightbox: { enabled: true } }) } });
        await settle();

        expect(bindLightbox).toHaveBeenCalledWith(wrapper.find('figure').element);

        bindLightbox.mockClear();
        mount(BlockTree, { props: { tree: image({}) } });
        await settle();

        expect(bindLightbox).not.toHaveBeenCalled();
    });
});

describe('GalleryBlock', () => {
    it('tells the overlay a non-default navigation button type', async () => {
        expect(await ssr([makeBlock('core/gallery', { navigationButtonType: 'both' })] as Block[])).toContain(
            'data-ap-lightbox-navigation="both"'
        );
    });

    it('prints nothing for the default', async () => {
        expect(await ssr([makeBlock('core/gallery', { navigationButtonType: 'icon' })] as Block[])).not.toContain(
            'data-ap-lightbox-navigation'
        );
    });
});

describe('setLightboxLabels', () => {
    afterEach(() => {
        setLightboxLabels();
    });

    it('translates the overlay labels and the trigger name', async () => {
        setLightboxLabels({ close: 'Schließen', enlargeWithAlt: 'Vergrößern: :alt' });

        const html = await ssr(image({ lightbox: { enabled: true } }));
        const labels = /data-ap-lightbox-labels="([^"]*)"/.exec(html)?.[1].replace(/&quot;/g, '"') ?? '{}';

        expect(JSON.parse(labels)).toMatchObject({ close: 'Schließen', dialog: 'Enlarged image', position: 'Image :current of :total' });
        expect(html).toContain('aria-label="Vergrößern: A cat"');
    });

    it('keeps the English defaults until it is called', async () => {
        const html = await ssr(image({ lightbox: { enabled: true } }));

        expect(html).toContain('aria-label="Enlarge image: A cat"');
    });
});
