/**
 * Per-instance layout scope tests (#871) — Vue renderer.
 *
 * The `wp-container-<hash>` scope and its rules must match the Blade
 * `LayoutSupport::container()` byte-for-byte; the markup-parity goldens pin
 * the hashes, so these cover the tree walk and the edge cases.
 */

import { describe, expect, it } from 'vitest';
import { applyBlockGap, applyColumnsGap } from '../src/support/blockGap';
import { blockGapValue, layoutContainer, layoutModifierClasses, stampLayoutScopes } from '../src/support/layoutContainer';
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

describe('layoutContainer block gap (M-15)', () => {
    const css = (attributes: Record<string, unknown>, layoutClassName: string, slug = 'group') =>
        layoutContainer(attributes, slug, layoutClassName)?.css ?? null;

    it('scopes a flow group gap to its children, matching the Blade golden', () => {
        const container = layoutContainer({ style: { spacing: { blockGap: '5rem' } } }, 'group', 'is-layout-flow');

        expect(container?.className).toBe('wp-container-f4f1e681cf25');
        expect(container?.css).toBe(
            '.wp-block-group.wp-block-group-is-layout-flow.wp-container-f4f1e681cf25 > * + *{margin-block-start:5rem;margin-block-end:0;}'
        );
    });

    it('adds the gap to the flex and grid container rule', () => {
        expect(css({ layout: { flexWrap: 'nowrap' }, style: { spacing: { blockGap: 'var:preset|spacing|40' } } }, 'is-layout-flex')).toMatch(
            /\{flex-wrap:nowrap;gap:var\(--wp--preset--spacing--40\);\}$/
        );
        expect(css({ style: { spacing: { blockGap: '1rem' } } }, 'is-layout-flex')).toMatch(/\{gap:1rem;\}$/);
        expect(css({ layout: { columnCount: 2 }, style: { spacing: { blockGap: '2rem' } } }, 'is-layout-grid')).toMatch(
            /\{grid-template-columns:repeat\(2, minmax\(0, 1fr\)\);gap:2rem;\}$/
        );
    });

    it('combines a per-axis gap with the fallback for the unset side', () => {
        expect(css({ style: { spacing: { blockGap: { top: '1rem' } } } }, 'is-layout-flex')).toMatch(/\{gap:1rem 0\.5em;\}$/);
        expect(css({ style: { spacing: { blockGap: { top: '2rem', left: '2rem' } } } }, 'is-layout-flex')).toMatch(/\{gap:2rem;\}$/);
        expect(
            layoutContainer({ style: { spacing: { blockGap: { left: '3rem' } } } }, 'columns', 'is-layout-flex', null, '2em')?.css
        ).toMatch(/\{gap:2em 3rem;\}$/);
        expect(css({ style: { spacing: { blockGap: {} } } }, 'is-layout-flex')).toBeNull();
    });

    it('spaces flow and constrained children with the top side only', () => {
        expect(css({ style: { spacing: { blockGap: { top: '2rem', left: '1rem' } } } }, 'is-layout-constrained')).toBe(
            css({ style: { spacing: { blockGap: '2rem' } } }, 'is-layout-constrained')
        );
        expect(css({ style: { spacing: { blockGap: { left: '1rem' } } } }, 'is-layout-flow')).toBeNull();
    });

    it('drops a gap that could add declarations (F-10)', () => {
        for (const blockGap of ['1rem;position:fixed;inset:0', '2rem}body{color:red', 'calc(1rem', '1rem)', '1rem/*', '   ', true]) {
            expect(css({ style: { spacing: { blockGap } } }, 'is-layout-flex')).toBeNull();
            expect(css({ style: { spacing: { blockGap } } }, 'is-layout-flow')).toBeNull();
        }
    });

    it('keys a custom wrapper class into the selector and the hash', () => {
        const attributes = { style: { spacing: { blockGap: '3rem' } } };
        const inner = layoutContainer(attributes, 'cover', 'is-layout-flow', 'wp-block-cover__inner-container');
        const outer = layoutContainer(attributes, 'cover', 'is-layout-flow');

        expect(inner?.css).toContain(`.wp-block-cover__inner-container.wp-block-cover-is-layout-flow.${inner?.className} > * + *`);
        expect(inner?.className).not.toBe(outer?.className);
    });
});

describe('blockGapValue (F-10)', () => {
    it('expands preset references and keeps safe values', () => {
        expect(blockGapValue('var:preset|spacing|40')).toBe('var(--wp--preset--spacing--40)');
        expect(blockGapValue(' 1.5rem ')).toBe('1.5rem');
        expect(blockGapValue('calc(1rem + 2px)')).toBe('calc(1rem + 2px)');
        expect(blockGapValue(12)).toBe('12');
    });

    it('rejects values that could break out of the declaration', () => {
        for (const value of ['1rem;position:fixed', '1rem}', 'url(//x)', 'calc(1rem', '', null, [], {}]) {
            expect(blockGapValue(value)).toBeNull();
        }
    });

    it('sanitizes the inline style the same way', () => {
        expect(applyBlockGap({ style: { spacing: { blockGap: '1rem;position:fixed;inset:0' } } })).toEqual({});
        expect(applyBlockGap({ style: { spacing: { blockGap: 'var:preset|spacing|40' } } })).toEqual({
            '--wp--style--block-gap': 'var(--wp--preset--spacing--40)',
        });
        expect(applyBlockGap({ style: { spacing: { blockGap: { top: '2rem}body{color:red', left: '1rem' } } } })).toEqual({
            columnGap: '1rem',
        });
    });
});

describe('applyColumnsGap (#914)', () => {
    it('hands a per-axis left gap to the Column width calc', () => {
        expect(applyColumnsGap({ style: { spacing: { blockGap: { top: '0', left: '0' } } } })).toEqual({
            '--ap-columns-gap': '0px',
        });
        expect(applyColumnsGap({ style: { spacing: { blockGap: { left: '4rem' } } } })).toEqual({
            '--ap-columns-gap': '4rem',
        });
        expect(applyColumnsGap({ style: { spacing: { blockGap: { left: 'var:preset|spacing|40' } } } })).toEqual({
            '--ap-columns-gap': 'var(--wp--preset--spacing--40)',
        });
    });

    it('stays unset without a usable per-axis left gap', () => {
        expect(applyColumnsGap({ style: { spacing: { blockGap: { top: '1rem' } } } })).toEqual({});
        expect(applyColumnsGap({ style: { spacing: { blockGap: { top: '1rem', left: '1rem;position:fixed' } } } })).toEqual({});
        expect(applyColumnsGap({ style: { spacing: { blockGap: '2rem' } } })).toEqual({});
        expect(applyColumnsGap({ style: { spacing: { blockGap: { left: '12' } } } })).toEqual({});
        expect(applyColumnsGap({})).toEqual({});
    });

    it('pins normal to 0px and drops other keywords, since the width calc needs a length', () => {
        for (const blockGap of [{ top: '1rem', left: 'normal' }, { left: 'NORMAL' }, 'Normal']) {
            expect(applyColumnsGap({ style: { spacing: { blockGap } } })).toEqual({ '--ap-columns-gap': '0px' });
        }

        for (const blockGap of [{ left: 'inherit' }, { left: 'auto' }, 'unset']) {
            expect(applyColumnsGap({ style: { spacing: { blockGap } } })).toEqual({});
        }
    });
});

describe('layoutModifierClasses (F-3)', () => {
    it('adds the orientation, justification and wrap classes WordPress adds', () => {
        expect(
            layoutModifierClasses({ layout: { orientation: 'vertical', justifyContent: 'space-between', flexWrap: 'nowrap' } })
        ).toEqual(['is-vertical', 'is-content-justification-space-between', 'is-nowrap']);
        expect(layoutModifierClasses({ layout: { orientation: 'vertical', justifyContent: 'left' } }, false, false)).toEqual([]);
    });

    it('mints classes only from allowlisted values', () => {
        expect(
            layoutModifierClasses({ layout: { orientation: 'diagonal" x', justifyContent: 'center x', flexWrap: 'wrap' } })
        ).toEqual([]);
        expect(layoutModifierClasses({ layout: 'grid' })).toEqual([]);
    });
});

describe('stampLayoutScopes for every layout block (F-3)', () => {
    const scopeOf = (node: Block): unknown => (node as { attributes: Record<string, unknown> }).attributes._veLayoutScope;

    it('stamps buttons, column, cover, navigation, query, quote, details and comments pagination', () => {
        const gap = { style: { spacing: { blockGap: '1rem' } } };
        const names = [
            'core/buttons',
            'artisanpack/column',
            'core/cover',
            'artisanpack/navigation',
            'core/query',
            'core/quote',
            'artisanpack/details',
            'artisanpack/comments-pagination',
        ];
        const { tree, css } = stampLayoutScopes(names.map((name) => block(name, gap)));

        for (const node of tree) {
            expect(scopeOf(node)).toMatch(/^wp-container-[0-9a-f]{12}$/);
        }
        expect(css).toContain('.wp-block-buttons.wp-block-buttons-is-layout-flex.');
        expect(css).toContain('.wp-block-column.wp-block-column-is-layout-flow.');
        expect(css).toContain('.wp-block-cover__inner-container.wp-block-cover-is-layout-flow.');
        expect(css).toContain('.wp-block-navigation.wp-block-navigation-is-layout-flex.');
        expect(css).toContain('.wp-block-comments-pagination.wp-block-comments-pagination-is-layout-flex.');
    });

    it('leaves columns, gallery, post-template and query pagination to their own layout handling', () => {
        const gap = { style: { spacing: { blockGap: '1rem' }, layout: { justifyContent: 'center' } } };
        const { tree, css } = stampLayoutScopes([
            block('core/columns', gap),
            block('core/gallery', gap),
            block('core/post-template', gap),
            block('core/query-pagination', { ...gap, layout: { type: 'flex', justifyContent: 'center' } }),
        ]);

        for (const node of tree) {
            expect(scopeOf(node)).toBeUndefined();
        }
        expect(css).toBe('');
    });

    it('drops vertical alignment from the navigation scope', () => {
        expect(stampLayoutScopes([block('core/navigation', { layout: { type: 'flex', verticalAlignment: 'bottom' } })]).css).toBe('');
        expect(stampLayoutScopes([block('core/navigation', { layout: { verticalAlignment: 'bottom' } })]).css).toBe('');
    });

    it('scopes the legacy navigation orientation and justification like Blade', () => {
        const { css } = stampLayoutScopes([
            block('core/navigation', { orientation: 'vertical', itemsJustification: 'right' }),
            block('core/navigation', { itemsJustification: 'center', layout: { justifyContent: 'left' } }),
        ]);

        expect(css).toContain('{flex-direction:column;align-items:flex-end;}');
        expect(css).toContain('{justify-content:flex-start;}');
        expect(css).not.toContain('justify-content:center');
    });
});
