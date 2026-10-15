/**
 * Per-instance layout scope tests (#871).
 *
 * The `wp-container-<hash>` scope and its rules must match the Blade
 * `LayoutSupport::container()` byte-for-byte; the markup-parity goldens pin
 * the hashes, so these cover the tree walk and the edge cases.
 */

import { describe, expect, it } from 'vitest';
import { layoutContainer, stampLayoutScopes } from '../src/support/layoutContainer';
import type { Block } from '../src/types';

function block(name: string, attributes: Record<string, unknown>, innerBlocks: Block[] = []): Block {
    return { name, attributes, innerBlocks } as unknown as Block;
}

describe('layoutContainer', () => {
    it('matches the Blade golden for a constrained group', () => {
        const container = layoutContainer(
            { layout: { type: 'constrained', contentSize: '640px', wideSize: '1200px', justifyContent: 'left' } },
            'group',
            'is-layout-constrained'
        );

        expect(container?.className).toBe('wp-container-f64cfa736147');
        expect(container?.css).toContain(
            '.wp-block-group.wp-block-group-is-layout-constrained.wp-container-f64cfa736147 > .alignwide{max-width:1200px;}'
        );
    });

    it('drops unsafe or unbalanced lengths like Blade', () => {
        for (const contentSize of ['640px;}body{color:red', 'calc(100% - 2rem', '640px)', '640px/*', '   ']) {
            expect(layoutContainer({ layout: { contentSize } }, 'group', 'is-layout-constrained')).toBeNull();
        }
    });

    it('accepts whole column counts and rejects the rest', () => {
        const css = (columnCount: unknown) =>
            layoutContainer({ layout: { type: 'grid', columnCount } }, 'group', 'is-layout-grid')?.css ?? '';

        expect(css(2)).toContain('repeat(2, minmax(0, 1fr))');
        expect(css(' 5 ')).toContain('repeat(5, minmax(0, 1fr))');

        for (const invalid of [2.5, 0, -2, 65, true]) {
            expect(css(invalid)).toContain('auto-fill');
        }
    });

    it('emits nothing for flow layouts or default flex', () => {
        expect(layoutContainer({ layout: { contentSize: '640px' } }, 'group', 'is-layout-flow')).toBeNull();
        expect(layoutContainer({ layout: { type: 'flex' } }, 'group', 'is-layout-flex')).toBeNull();
    });
});

describe('stampLayoutScopes', () => {
    it('stamps group and post-content blocks, deduping identical rules', () => {
        const layout = { type: 'grid', columnCount: 2 };
        const { tree, css } = stampLayoutScopes([
            block('core/group', { layout }),
            block('artisanpack/group', { layout }, [block('core/post-content', { layout: { type: 'constrained', contentSize: '700px' } })]),
        ]);

        const first = (tree[0] as { attributes: Record<string, unknown> }).attributes._veLayoutScope;
        const second = (tree[1] as { attributes: Record<string, unknown> }).attributes._veLayoutScope;
        const nested = ((tree[1] as { innerBlocks: Block[] }).innerBlocks[0] as { attributes: Record<string, unknown> })
            .attributes._veLayoutScope;

        expect(first).toMatch(/^wp-container-[0-9a-f]{12}$/);
        expect(second).toBe(first);
        expect(nested).toMatch(/^wp-container-[0-9a-f]{12}$/);
        expect(css.match(/grid-template-columns/g)).toHaveLength(1);
        expect(css).toContain('.wp-block-post-content.wp-block-post-content-is-layout-constrained');
    });

    it('strips an injected scope from blocks it does not stamp', () => {
        const { tree, css } = stampLayoutScopes([
            block('core/group', { layout: { type: 'flow' }, _veLayoutScope: 'evil"><script>' }),
            block('core/paragraph', { _veLayoutScope: 'evil' }),
        ]);

        for (const node of tree) {
            expect((node as { attributes: Record<string, unknown> }).attributes).not.toHaveProperty('_veLayoutScope');
        }
        expect(css).toBe('');
    });

    it('uses the flex layout class for a group the Flex Layout panel switched to flex', () => {
        const { css } = stampLayoutScopes([
            block('core/group', {
                artisanpackFlex: { container: { enabled: { base: true } } },
                layout: { justifyContent: 'center' },
            }),
        ]);

        expect(css).toContain('.wp-block-group.wp-block-group-is-layout-flex.');
        expect(css).toContain('{justify-content:center;}');
    });
});
