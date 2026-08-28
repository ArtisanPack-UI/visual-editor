/**
 * Grid Item — deprecations.
 *
 * v1: pre-#747 markup. The grid-item block shipped a null `save` while
 * its `edit` hosted inner blocks, so Gutenberg serialized only the auto
 * wrapper class — legacy theme templates/patterns persisted
 * `<div class="wp-block-artisanpack-grid-item">` + inner blocks with no
 * `ap-grid-item*` classes. This entry reproduces that wrapper so the old
 * markup validates and migrates to the current `ap-grid-item*` save.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';

const v1 = {
    attributes: {
        innerLayout: { type: 'string', default: 'normal' },
        gridColumnSpan: { type: 'number', default: 1 },
        gridRowSpan: { type: 'number', default: 1 },
        artisanpackFlex: { type: 'object' },
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
