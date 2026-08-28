/**
 * Grid — deprecations.
 *
 * v1: pre-#747 markup. The grid block shipped a null `save` while its
 * `edit` hosted inner `grid-item`s, so Gutenberg serialized only the
 * auto wrapper class — legacy theme templates/patterns persisted
 * `<div class="wp-block-artisanpack-grid">` + inner grid-items with no
 * `ap-grid*` layout classes. This entry reproduces that wrapper so the
 * old markup validates and migrates to the current `ap-grid*` save.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';

const v1 = {
    attributes: {
        numColumns: { type: 'number', default: 4 },
        layoutMode: { type: 'string' },
        photoGrid: { type: 'object' },
    },
    supports: {
        className: true,
    },
    save() {
        const blockProps = (useBlockProps.save as any)();
        const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
        return createElement('div', innerBlocksProps);
    },
};

const deprecated = [v1];

export default deprecated;
