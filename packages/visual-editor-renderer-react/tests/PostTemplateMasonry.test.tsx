/**
 * Issue #593 — masonry layout option on artisanpack/post-template.
 *
 * Verifies the React renderer emits the `is-layout-masonry` wrapper
 * class + `data-ap-cols` attribute when `layout: masonry` is set, while
 * preserving the existing `is-layout-flow` default and `is-layout-grid`
 * path.
 */

import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { makeBlock, normalizeHtml } from './helpers';

function renderTree(tree: unknown): string {
    const { container } = render(
        <BlockTree tree={tree as Parameters<typeof BlockTree>[0]['tree']} />
    );
    return normalizeHtml(container.innerHTML);
}

describe('artisanpack/post-template — masonry layout (#593)', () => {
    it('emits is-layout-masonry + columns-N + data-ap-cols when layout is masonry', () => {
        const html = renderTree([
            makeBlock('artisanpack/post-template', {
                layout: 'masonry',
                columns: 4,
            }),
        ]);

        expect(html).toContain('is-layout-masonry');
        // Masonry layers is-layout-grid underneath so Gutenberg's bundled
        // layout baseline provides `display: grid` inside the editor
        // canvas iframe and the post-template grid CSS sets columns-N.
        expect(html).toContain('is-layout-grid');
        expect(html).toContain('columns-4');
        expect(html).toContain('data-ap-cols="4"');
        expect(html).not.toContain('is-layout-flow');
    });

    it('keeps the existing is-layout-grid path for layout: grid', () => {
        const html = renderTree([
            makeBlock('artisanpack/post-template', {
                layout: 'grid',
                columns: 3,
            }),
        ]);

        expect(html).toContain('is-layout-grid');
        expect(html).toContain('columns-3');
        expect(html).not.toContain('is-layout-masonry');
        expect(html).not.toContain('data-ap-cols');
    });

    it('defaults to is-layout-flow without columns-N or data-ap-cols', () => {
        const html = renderTree([
            makeBlock('artisanpack/post-template', {}),
        ]);

        expect(html).toContain('is-layout-flow');
        expect(html).not.toContain('is-layout-masonry');
        expect(html).not.toContain('is-layout-grid');
        expect(html).not.toContain('data-ap-cols');
    });
});
