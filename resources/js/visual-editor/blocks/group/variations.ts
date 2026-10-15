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
import { TEXT_DOMAIN } from '../../vendor/i18n';

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
            attributes: { content: __('One.', TEXT_DOMAIN) },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Two.', TEXT_DOMAIN) },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Three.', TEXT_DOMAIN) },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Four.', TEXT_DOMAIN) },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Five.', TEXT_DOMAIN) },
        },
        {
            name: 'core/paragraph',
            attributes: { content: __('Six.', TEXT_DOMAIN) },
        },
    ],
};

const variations: Variation[] = [
    {
        name: 'group',
        title: __('Group', TEXT_DOMAIN),
        description: __('Gather blocks in a container.', TEXT_DOMAIN),
        attributes: { layout: { type: 'constrained' } },
        isDefault: true,
        scope: ['block', 'inserter', 'transform'],
    },
    {
        name: 'row',
        title: _x('Row', 'single horizontal line', TEXT_DOMAIN),
        description: __('Arrange blocks horizontally.', TEXT_DOMAIN),
        attributes: { layout: { type: 'flex', flexWrap: 'nowrap' } },
        scope: ['block', 'inserter', 'transform'],
        isActive: ['layout.type'],
        example,
    },
    {
        name: 'stack',
        title: __('Stack', TEXT_DOMAIN),
        description: __('Arrange blocks vertically.', TEXT_DOMAIN),
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
