/**
 * Icon — saved markup.
 *
 * Phase 1 (#552). The actual SVG body is resolved server-side by
 * Phase 2's `IconBlock` dynamic renderer — this save shape only
 * persists the attribute envelope plus the link wrapper. The
 * server reads the `data-*` attributes back, looks the icon up in
 * the registry, and emits the inline `<svg>`.
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

import type { IconAttributes } from './types';
import {
    composeRel,
    computeIconStyle,
    computeTransform,
    normalizeAttributes,
    normalizeLinkTarget,
    shouldRenderLink,
} from './utils';

interface IconSaveProps {
    readonly attributes: IconAttributes;
}

export default function IconSave( { attributes }: IconSaveProps ): ReactElement {
    const normalized = normalizeAttributes( attributes );
    const transform = computeTransform( normalized );
    // No wrapper style here — `useBlockProps.save` already serializes
    // WP-managed background/border/spacing onto the wrapper div via the
    // block.json `supports` map.
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
        // No icon selected — emit a true placeholder so the saved markup
        // matches IconBlock::renderBody()'s placeholder branch and screen
        // readers don't see a phantom "ref" element with iconRef data.
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
        // Decorative icons hide the body's ariaLabel via aria-hidden; promote
        // the label onto the anchor so the link still has an accessible name.
        const anchorAriaLabel =
            normalized.isDecorative && normalized.ariaLabel
                ? normalized.ariaLabel
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
