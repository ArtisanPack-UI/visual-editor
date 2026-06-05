/**
 * TemplatePart — transforms.
 *
 * Bidirectional `core/template-part` ↔ `artisanpack/template-part` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/template-part` direction is removed at the I7 cutover once
 * `core/template-part` is no longer registered. Phase I5 entity cluster (#413).
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface EntityAttributes {
    readonly [ key: string ]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/template-part' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/template-part' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/template-part', attributes ),
        },
    ],
};

export default transforms;
