/**
 * Per-block layout compound classes — React renderer (#702).
 *
 * Every layout-supporting wrapper carries the shared `is-layout-{type}`
 * modifier AND the `wp-block-{slug}-is-layout-{type}` compound the
 * block-library stylesheet actually targets. The expected strings here
 * mirror the Blade renderer's partials (fixed in #700) so the three
 * renderers stay interchangeable.
 */

import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { LAYOUT_BASELINE_CSS } from '../src/support/layoutBaselineCss';
import { layoutClass, layoutPair } from '../src/support/attributes';
import { makeBlock, normalizeHtml } from './helpers';

function renderTree(tree: unknown): string {
    const { container } = render(
        <BlockTree tree={tree as Parameters<typeof BlockTree>[0]['tree']} />
    );

    return normalizeHtml(container.innerHTML);
}

describe('layoutClass()', () => {
    it('resolves each supported layout type', () => {
        expect(layoutClass({ layout: { type: 'constrained' } })).toBe('is-layout-constrained');
        expect(layoutClass({ layout: { type: 'flex' } })).toBe('is-layout-flex');
        expect(layoutClass({ layout: { type: 'grid' } })).toBe('is-layout-grid');
        expect(layoutClass({ layout: { type: 'flow' } })).toBe('is-layout-flow');
    });

    it('falls back rather than minting a class token from an unknown type', () => {
        expect(layoutClass({ layout: { type: 'evil onerror=x' } })).toBe('is-layout-flow');
        expect(layoutClass({})).toBe('is-layout-flow');
        expect(layoutClass({ layout: 'not-an-object' })).toBe('is-layout-flow');
        expect(layoutClass({}, 'constrained')).toBe('is-layout-constrained');
        expect(layoutClass({}, 'bogus')).toBe('is-layout-flow');
    });
});

describe('layoutPair()', () => {
    it('emits the shared modifier followed by the per-block compound', () => {
        expect(layoutPair('group', 'is-layout-flex')).toEqual([
            'is-layout-flex',
            'wp-block-group-is-layout-flex',
        ]);
    });

    it('skips empty entries', () => {
        expect(layoutPair('group', '')).toEqual([]);
    });
});

describe('layout-supporting wrappers emit the compound class', () => {
    it('pairs the group layout class', () => {
        const tree = [makeBlock('core/group', { layout: { type: 'constrained' } }, [])];

        expect(renderTree(tree)).toContain(
            'class="wp-block-group is-layout-constrained wp-block-group-is-layout-constrained"'
        );
    });

    it('renders a grid group as is-layout-grid rather than silently as flow', () => {
        const tree = [makeBlock('core/group', { layout: { type: 'grid' } }, [])];

        expect(renderTree(tree)).toContain(
            'class="wp-block-group is-layout-grid wp-block-group-is-layout-grid"'
        );
    });

    it('keys row and stack on the group wrapper they actually render', () => {
        expect(renderTree([makeBlock('core/row', {}, [])])).toContain(
            'class="wp-block-group is-layout-flex wp-block-group-is-layout-flex is-horizontal"'
        );
        expect(renderTree([makeBlock('core/stack', {}, [])])).toContain(
            'class="wp-block-group is-layout-flex wp-block-group-is-layout-flex is-vertical"'
        );
    });

    it('emits the flex layout classes on columns', () => {
        const tree = [makeBlock('core/columns', {}, [])];

        expect(renderTree(tree)).toContain(
            'class="wp-block-columns is-layout-flex wp-block-columns-is-layout-flex is-stacked-on-mobile"'
        );
    });

    it('pairs the buttons layout class', () => {
        const tree = [makeBlock('core/buttons', {}, [])];

        expect(renderTree(tree)).toContain(
            'class="wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex is-content-justification-left"'
        );
    });

    it('surfaces the post-content layout attribute', () => {
        const tree = [
            makeBlock('core/post-content', {
                _resolvedContent: '<p>Body</p>',
                layout: { type: 'constrained' },
            }),
        ];

        expect(renderTree(tree)).toContain(
            'class="entry-content wp-block-post-content is-layout-constrained wp-block-post-content-is-layout-constrained"'
        );
    });

    it('defaults post-content to flow when no layout is stored', () => {
        const tree = [makeBlock('core/post-content', { _resolvedContent: '<p>Body</p>' })];

        expect(renderTree(tree)).toContain(
            'class="entry-content wp-block-post-content is-layout-flow wp-block-post-content-is-layout-flow"'
        );
    });

    it('pairs the post-template layout class and leaves masonry unpaired', () => {
        expect(renderTree([makeBlock('core/post-template', {}, [])])).toContain(
            'class="wp-block-post-template is-layout-flow wp-block-post-template-is-layout-flow"'
        );

        const masonry = renderTree([
            makeBlock('core/post-template', { layout: 'masonry', columns: 3 }, []),
        ]);

        expect(masonry).toContain(
            'wp-block-post-template is-layout-grid wp-block-post-template-is-layout-grid is-layout-masonry columns-3'
        );
        expect(masonry).not.toContain('wp-block-post-template-is-layout-masonry');
    });
});

describe('LAYOUT_BASELINE_CSS', () => {
    it('constrains unaligned children of a constrained group', () => {
        expect(LAYOUT_BASELINE_CSS).toContain(
            '.wp-block-group.wp-block-group-is-layout-constrained > :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright)) { max-width: var(--wp--style--global--content-size);'
        );
        expect(LAYOUT_BASELINE_CSS).toContain(
            '.wp-block-group.wp-block-group-is-layout-constrained > .alignwide { max-width: var(--wp--style--global--wide-size);'
        );
        expect(LAYOUT_BASELINE_CSS).toContain(
            '.wp-block-group.wp-block-group-is-layout-constrained > .alignfull { max-width: none; }'
        );
    });
});
