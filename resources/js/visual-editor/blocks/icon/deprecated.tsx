/**
 * Icon — deprecation chain.
 *
 * `artisanpack/icon` is a dynamic (server-rendered) block: its public
 * markup is produced by {@see IconBlock::render()} on the server, and the
 * Blade / React / Vue renderers re-render from the saved attributes. The
 * block therefore follows the package's dynamic-block convention and now
 * saves `null` (see `save.tsx`).
 *
 * Earlier releases (#552 through the Phase 7 / position-support work)
 * shipped a `save` that serialized the full wrapper `<div>` + body
 * `<span>` into post content. Any icon saved by those builds carries that
 * markup, so re-opening it against the new `null` save would fail block
 * validation with "unexpected or invalid content" — and, because there
 * was no deprecation to fall back to, "Attempt Block Recovery" downgraded
 * the icon to a paragraph (#749).
 *
 * This single deprecation reproduces that legacy markup. When Gutenberg
 * meets the old `<div><span>…</span></div>` body it matches here, keeps
 * the block as `artisanpack/icon`, and re-saves it in the null-save form
 * on the next update — no attribute changes required.
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

import metadata from './block.json';
import type { IconAttributes } from './types';
import {
    composeRel,
    computeIconStyle,
    computeTransform,
    normalizeAttributes,
    normalizeLinkTarget,
    shouldRenderLink,
} from './utils';

/**
 * The pre-#749 markup save. Kept byte-for-byte identical to the original
 * `IconSave` so already-persisted icons validate against it.
 */
function legacyIconSave( { attributes }: { attributes: IconAttributes } ): ReactElement {
    const normalized = normalizeAttributes( attributes );
    const transform = computeTransform( normalized );
    const blockProps = useBlockProps.save();
    const sizedStyle = computeIconStyle( normalized );

    const dataAttrs: Record< string, string > = {};
    if ( normalized.iconRef ) {
        dataAttrs[ 'data-icon-set' ] = normalized.iconRef.set;
        dataAttrs[ 'data-icon-name' ] = normalized.iconRef.name;
    }
    if ( transform ) {
        dataAttrs[ 'data-icon-transform' ] = transform;
    }

    const ariaProps: Record< string, string > = {};
    if ( normalized.isDecorative ) {
        ariaProps[ 'aria-hidden' ] = 'true';
    } else if ( normalized.ariaLabel ) {
        ariaProps[ 'aria-label' ] = normalized.ariaLabel;
    }
    if ( normalized.titleAttr ) {
        ariaProps.title = normalized.titleAttr;
    }

    const innerStyle = { ...sizedStyle, transform, transformOrigin: 'center' as const };

    let body;
    if ( normalized.customSvg.trim().length > 0 ) {
        body = (
            <span
                className="wp-block-artisanpack-icon__svg"
                style={ innerStyle }
                { ...ariaProps }
                dangerouslySetInnerHTML={ { __html: normalized.customSvg } }
            />
        );
    } else if ( normalized.iconRef ) {
        body = (
            <span
                className="wp-block-artisanpack-icon__ref"
                style={ innerStyle }
                { ...ariaProps }
                { ...dataAttrs }
            />
        );
    } else {
        body = (
            <span
                className="wp-block-artisanpack-icon__placeholder"
                style={ innerStyle }
                aria-hidden="true"
            />
        );
    }

    if ( shouldRenderLink( normalized ) ) {
        const target = normalizeLinkTarget( normalized.linkTarget );
        const rel = composeRel( normalized.linkTarget, normalized.linkRel );
        const trimmedAriaLabel = normalized.ariaLabel.trim();
        const anchorAriaLabel =
            normalized.isDecorative && trimmedAriaLabel
                ? trimmedAriaLabel
                : undefined;

        return (
            <div { ...blockProps }>
                <a
                    href={ normalized.link }
                    target={ target || undefined }
                    rel={ rel || undefined }
                    aria-label={ anchorAriaLabel }
                >
                    { body }
                </a>
            </div>
        );
    }

    return <div { ...blockProps }>{ body }</div>;
}

const deprecated = [
    {
        attributes: metadata.attributes as Record< string, unknown >,
        supports: metadata.supports as Record< string, unknown >,
        save: legacyIconSave,
    },
];

export default deprecated;
