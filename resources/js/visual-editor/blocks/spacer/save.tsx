/**
 * Spacer — save component.
 *
 * Ported from `@wordpress/block-library/src/spacer/save.js` (v9.43.0).
 * Adds an explicit `wp-block-spacer` class so the saved markup is
 * byte-equivalent regardless of namespace.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    useBlockProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    getSpacingPresetCssVar,
} from '@wordpress/block-editor';

interface SpacerLayout {
    selfStretch?: string;
    flexSize?: string;
}

interface SpacerSaveAttributes {
    readonly height?: string;
    readonly width?: string;
    readonly style?: { layout?: SpacerLayout; [key: string]: unknown };
}

export default function spacerSave({
    attributes,
}: {
    attributes: SpacerSaveAttributes;
}): ReactElement {
    const { height, width, style } = attributes;
    const { layout: { selfStretch } = {} } = style || {};
    const finalHeight =
        selfStretch === 'fill' || selfStretch === 'fit' ? undefined : height;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps.save as any)({
        className: clsx('wp-block-spacer'),
        style: {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            height: (getSpacingPresetCssVar as any)(finalHeight),
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            width: (getSpacingPresetCssVar as any)(width),
        },
        'aria-hidden': true,
    });

    return <div {...blockProps} />;
}
