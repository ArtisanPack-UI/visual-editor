/**
 * Group — block variations.
 *
 * Ported from `@wordpress/block-library/src/group/variations.js`
 * (v9.43.0). Ports the `group`, `row`, and `stack` variations.
 *
 * NOTE: The upstream `grid` variation is intentionally EXCLUDED.
 * Per docs/plans/13-block-fork.md §2.2.1, grid is split into a
 * standalone `artisanpack/grid` + `artisanpack/grid-item` block pair
 * at a later stage rather than living as a Group variation.
 *
 * `innerBlocks` references in row/stack are left pointing at the
 * original block names (none here — only `attributes` payloads ship
 * with the variations themselves, so no namespace rewrite needed).
 */

import { __, _x } from '@wordpress/i18n';

interface Variation {
    name: string;
    title: string;
    description: string;
    attributes: Record<string, unknown>;
    isDefault?: boolean;
    scope: Array<'block' | 'inserter' | 'transform'>;
    isActive?: string[];
    icon?: unknown;
    example?: unknown;
}

const example = {
    innerBlocks: [
        {
            name: 'core/paragraph',
            attributes: { content: __('One.') },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Two.') },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Three.') },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Four.') },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Five.') },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Six.') },
        },
    ],
};

const variations: Variation[] = [
    {
        name: 'group',
        title: __('Group'),
        description: __('Gather blocks in a container.'),
        attributes: { layout: { type: 'constrained' } },
        isDefault: true,
        scope: ['block', 'inserter', 'transform'],
    },
    {
        name: 'row',
        title: _x('Row', 'single horizontal line'),
        description: __('Arrange blocks horizontally.'),
        attributes: { layout: { type: 'flex', flexWrap: 'nowrap' } },
        scope: ['block', 'inserter', 'transform'],
        isActive: ['layout.type'],
        example,
    },
    {
        name: 'stack',
        title: __('Stack'),
        description: __('Arrange blocks vertically.'),
        attributes: { layout: { type: 'flex', orientation: 'vertical' } },
        scope: ['block', 'inserter', 'transform'],
        isActive: ['layout.type', 'layout.orientation'],
        example,
    },
    // NOTE: The upstream `grid` variation is intentionally NOT ported here.
    // It will be reintroduced as a standalone `artisanpack/grid` +
    // `artisanpack/grid-item` block pair (see docs/plans/13-block-fork.md §2.2.1).
];

export default variations;
