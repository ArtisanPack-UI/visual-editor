/**
 * Image and Gallery lightbox (#1046).
 */

import { render, waitFor } from '@testing-library/react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, describe, expect, it, vi } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { setLightboxLabels } from '../src/index';
import { imageHasLightbox, lightboxNavigationType, stampLightboxDefault } from '../src/support/lightbox';
import { makeBlock } from './helpers';

const { bindLightbox } = vi.hoisted(() => ({ bindLightbox: vi.fn() }));

vi.mock('../src/support/lightboxOverlay.generated.js', () => ({ bindLightbox }));

type Tree = Parameters<typeof BlockTree>[0]['tree'];

const URL = 'https://example.test/a.jpg';
const image = (attributes: Record<string, unknown>, name = 'artisanpack/image') =>
    [makeBlock(name, { url: URL, alt: 'A cat', ...attributes })] as Tree;

describe('imageHasLightbox', () => {
    it.each([
        ['off by default', { url: URL }, false],
        ['on for the block', { url: URL, lightbox: { enabled: true } }, true],
        ['off for the block', { url: URL, lightbox: { enabled: false } }, false],
        ['on from the theme', { url: URL, _veLightbox: true }, true],
        ['the block over the theme', { url: URL, lightbox: { enabled: false }, _veLightbox: true }, false],
        ['a link over the block', { url: URL, href: '/x', lightbox: { enabled: true } }, false],
        ['a link over the theme', { url: URL, href: '/x', _veLightbox: true }, false],
        ['nothing to enlarge', { lightbox: { enabled: true } }, false],
        ['a non-boolean value', { url: URL, lightbox: { enabled: 'yes' } }, false],
    ])('%s', (_label, attributes, expected) => {
        expect(imageHasLightbox(attributes)).toBe(expected);
    });

    it('treats an unsafe link as no link, since it does not print', () => {
        expect(imageHasLightbox({ url: URL, href: 'javascript:alert(1)', lightbox: { enabled: true } })).toBe(true);
    });
});

describe('lightboxNavigationType', () => {
    it.each([
        ['text', 'text'],
        ['both', 'both'],
        ['icon', undefined],
        ['x" onclick="y', undefined],
        [undefined, undefined],
    ])('%s', (type, expected) => {
        expect(lightboxNavigationType(type)).toBe(expected);
    });
});

describe('stampLightboxDefault', () => {
    it('reaches nested images and leaves other blocks alone', () => {
        const tree = [makeBlock('core/gallery', {}, [makeBlock('core/image', {})]), makeBlock('core/video', {})];
        const [gallery, video] = stampLightboxDefault(tree as never, true);

        expect(gallery.innerBlocks?.[0].attributes?._veLightbox).toBe(true);
        expect(gallery.attributes?._veLightbox).toBeUndefined();
        expect(video.attributes?._veLightbox).toBeUndefined();
    });

    it('returns the same tree when the default is off', () => {
        const tree = [makeBlock('core/image', {})] as never;

        expect(stampLightboxDefault(tree, false)).toBe(tree);
    });

    it('returns the same tree when every image is already stamped', () => {
        const tree = [makeBlock('core/image', { _veLightbox: true })] as never;

        expect(stampLightboxDefault(tree, true)).toBe(tree);
    });

    it('drops a saved _veLightbox when the default is off', () => {
        const tree = [makeBlock('core/group', {}, [makeBlock('core/image', { _veLightbox: true })])] as never;
        const [group] = stampLightboxDefault(tree, false);

        expect(group.innerBlocks?.[0].attributes).not.toHaveProperty('_veLightbox');
    });
});

describe('ImageBlock', () => {
    it('renders an existing image unchanged', () => {
        expect(renderToStaticMarkup(<BlockTree tree={image({})} />)).toBe(
            '<figure class="wp-block-image"><img src="https://example.test/a.jpg" alt="A cat"/></figure>'
        );
    });

    it('prints the container, labels and a hidden button trigger', () => {
        const html = renderToStaticMarkup(<BlockTree tree={image({ lightbox: { enabled: true } })} />);

        expect(html).toContain('class="wp-block-image wp-lightbox-container"');
        expect(html).toContain('data-ap-lightbox-labels=');
        expect(html).toMatch(
            /<button type="button" class="lightbox-trigger" aria-haspopup="dialog" aria-label="Enlarge image: A cat" style="display:none">/
        );
    });

    it('labels the trigger without alt text', () => {
        const html = renderToStaticMarkup(<BlockTree tree={image({ alt: '', lightbox: { enabled: true } })} />);

        expect(html).toContain('aria-label="Enlarge image"');
    });

    it.each([['core/image'], ['artisanpack/image']])('applies the theme default to %s', (name) => {
        const html = renderToStaticMarkup(<BlockTree tree={image({}, name)} lightboxDefault />);

        expect(html).toContain('wp-lightbox-container');
    });

    it('ignores a saved _veLightbox without a theme default, as Blade does', () => {
        const html = renderToStaticMarkup(
            <BlockTree tree={[makeBlock('core/image', { url: URL, _veLightbox: true })] as Tree} />
        );

        expect(html).not.toContain('wp-lightbox-container');
        expect(html).not.toContain('lightbox-trigger');
    });

    it('lets a block turn off the theme default', () => {
        const html = renderToStaticMarkup(
            <BlockTree tree={image({ lightbox: { enabled: false } })} lightboxDefault />
        );

        expect(html).not.toContain('wp-lightbox-container');
    });

    it('keeps the link and prints no lightbox on a linked image', () => {
        const html = renderToStaticMarkup(
            <BlockTree tree={image({ href: 'https://example.test/page', lightbox: { enabled: true } })} />
        );

        expect(html).toContain('<a href="https://example.test/page">');
        expect(html).not.toContain('lightbox');
    });

    it('binds the overlay in the browser, only when needed', async () => {
        bindLightbox.mockClear();

        const { container } = render(
            <BlockTree tree={image({ lightbox: { enabled: true } })} />
        );

        await waitFor(() => expect(bindLightbox).toHaveBeenCalledTimes(1));
        expect(bindLightbox).toHaveBeenCalledWith(container.querySelector('figure'));

        bindLightbox.mockClear();
        render(<BlockTree tree={image({})} />);
        await vi.dynamicImportSettled();

        expect(bindLightbox).not.toHaveBeenCalled();
    });
});

describe('GalleryBlock', () => {
    it('tells the overlay a non-default navigation button type', () => {
        const html = renderToStaticMarkup(
            <BlockTree tree={[makeBlock('core/gallery', { navigationButtonType: 'text' })] as Tree} />
        );

        expect(html).toContain('data-ap-lightbox-navigation="text"');
    });

    it('prints nothing for the default', () => {
        const html = renderToStaticMarkup(
            <BlockTree tree={[makeBlock('core/gallery', { navigationButtonType: 'icon' })] as Tree} />
        );

        expect(html).not.toContain('data-ap-lightbox-navigation');
    });
});

describe('setLightboxLabels', () => {
    afterEach(() => {
        setLightboxLabels();
    });

    it('translates the overlay labels and the trigger name', () => {
        setLightboxLabels({ close: 'Schließen', enlargeWithAlt: 'Vergrößern: :alt', position: 'Bild :current von :total' });

        const html = renderToStaticMarkup(<BlockTree tree={image({ lightbox: { enabled: true } })} />);
        const labels = /data-ap-lightbox-labels="([^"]*)"/.exec(html)?.[1].replace(/&quot;/g, '"') ?? '{}';

        expect(JSON.parse(labels)).toMatchObject({ close: 'Schließen', dialog: 'Enlarged image', position: 'Bild :current von :total' });
        expect(html).toContain('aria-label="Vergrößern: A cat"');
    });

    it('keeps a label passed as undefined or a non-string', () => {
        setLightboxLabels({ enlargeWithAlt: undefined, close: 7 as unknown as string });

        const html = renderToStaticMarkup(<BlockTree tree={image({ lightbox: { enabled: true } })} />);

        expect(html).toContain('aria-label="Enlarge image: A cat"');
        expect(html).toContain('&quot;close&quot;:&quot;Close&quot;');
    });

    it('keeps the English defaults until it is called', () => {
        const html = renderToStaticMarkup(<BlockTree tree={image({ lightbox: { enabled: true } })} />);

        expect(html).toContain('aria-label="Enlarge image: A cat"');
        expect(html).toContain('&quot;close&quot;:&quot;Close&quot;');
    });
});
