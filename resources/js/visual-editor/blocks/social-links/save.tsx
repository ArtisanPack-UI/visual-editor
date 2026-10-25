/**
 * Social Icons — save component (#1023).
 *
 * Ported from `@wordpress/block-library/src/social-links/save.js`
 * (v9.43.0). The `<ul>` wrapper is saved so Gutenberg can validate it;
 * each `artisanpack/social-link` child is dynamic and saves nothing. The
 * icon styles ride on the `<ul>` as `--ap-social-links-*` properties.
 */

import type { CSSProperties, ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

import { socialLinksClassName, socialLinksStyle, type SocialLinksStyleAttributes } from './utils';

interface SocialLinksSaveProps {
    readonly attributes: SocialLinksStyleAttributes;
}

export default function SocialLinksSave({ attributes }: SocialLinksSaveProps): ReactElement {
    const style = socialLinksStyle(attributes);
    const blockProps = useBlockProps.save({
        className: socialLinksClassName(attributes),
        ...(Object.keys(style).length > 0 ? { style: style as CSSProperties } : {}),
    });

    return <ul {...useInnerBlocksProps.save(blockProps)} />;
}
