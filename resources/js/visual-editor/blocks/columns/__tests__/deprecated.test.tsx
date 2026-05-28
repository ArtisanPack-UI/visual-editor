/**
 * Locks the deprecation chain for `artisanpack/columns`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: {
        Content: () => null,
    },
    getColorClassName: (prefix: string, slug?: string) =>
        slug ? `has-${slug}-${prefix}` : undefined,
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
}));

import deprecated from '../deprecated';

describe('columns deprecation chain', () => {
    it('ships three deprecation entries matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(3);
    });

    it('v1 migrates custom colors into the style.color shape', () => {
        const v1 = deprecated[0];
        const migrated = v1.migrate({
            customTextColor: '#fff',
            customBackgroundColor: '#000',
        });
        expect(migrated.style).toEqual({
            color: { text: '#fff', background: '#000' },
        });
        expect(migrated.isStackedOnMobile).toBe(true);
    });

    it('v1 renders a <div> wrapper', () => {
        const v1 = deprecated[0];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    verticalAlignment: 'center',
                    backgroundColor: undefined,
                    customBackgroundColor: undefined,
                    textColor: undefined,
                    customTextColor: undefined,
                },
            })
        );
        expect(html).toContain('<div');
        expect(html).toContain('are-vertically-aligned-center');
    });
});
