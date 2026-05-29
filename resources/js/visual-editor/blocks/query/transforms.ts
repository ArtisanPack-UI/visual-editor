/**
 * Query Loop — transforms.
 *
 * `@wordpress/block-library/src/query` ships variation-driven transforms,
 * which the fork does not carry (the variation picker pulls selectors the
 * post editor's core-data shim does not implement). Instead the fork ships
 * the bidirectional `core/query` ↔ `artisanpack/query` rollout transforms
 * plus a one-way `core/query-loop` → `artisanpack/query` conversion:
 * `core/query-loop` is the deprecated upstream alias for `core/query`, so
 * any pasted legacy `query-loop` markup folds into the fork. The
 * `to: core/query` direction is removed at the I7 cutover once
 * `core/query` is no longer registered. Phase I6 loop / feed
 * cluster (#414).
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface QueryAttributes {
    readonly [ key: string ]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/query' ],
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            transform: ( attributes: QueryAttributes, innerBlocks: any[] ) =>
                createBlock( name, attributes, innerBlocks ),
        },
        {
            type: 'block',
            blocks: [ 'core/query-loop' ],
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            transform: ( attributes: QueryAttributes, innerBlocks: any[] ) =>
                createBlock( name, attributes, innerBlocks ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/query' ],
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            transform: ( attributes: QueryAttributes, innerBlocks: any[] ) =>
                createBlock( 'core/query', attributes, innerBlocks ),
        },
    ],
};

export default transforms;
