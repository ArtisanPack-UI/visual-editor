/**
 * Icon — editor-side render.
 *
 * Phase 1 (#552). The picker, paste-svg, and admin-upload phases plug
 * additional controls in via the inspector — this file ships the
 * canvas render + a placeholder picker button so authors can verify
 * the block exists end-to-end.
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import type { IconAttributes } from './types';
import {
    composeRel,
    computeIconStyle,
    computeTransform,
    hasDecorativeLinkConflict,
    normalizeAttributes,
    normalizeLinkTarget,
    shouldRenderLink,
} from './utils';

interface IconEditProps {
    readonly attributes: IconAttributes;
    readonly setAttributes: ( next: Partial< IconAttributes > ) => void;
}

export default function IconEdit( { attributes, setAttributes }: IconEditProps ): ReactElement {
    const normalized = normalizeAttributes( attributes );
    const blockProps = useBlockProps();

    const sizedStyle = computeIconStyle( normalized );
    // Only the transform belongs here — sizedStyle already carries
    // width/height. Keeping width/height: '100%' would have overridden
    // the pixel dimensions when spread after sizedStyle, breaking
    // parity with save.tsx.
    const innerStyle = {
        transform: computeTransform( normalized ),
        transformOrigin: 'center' as const,
    };

    const placeholder = (
        <button
            type="button"
            className="wp-block-artisanpack-icon__placeholder-button"
            style={ sizedStyle }
            onClick={ () => {
                // Phase 4 (#555) wires the real picker. Until then we
                // open a tiny "type a slug" prompt so authors can still
                // exercise the block in dev environments.
                const setName = prompt( __( 'Icon set prefix (e.g. fas, far, fab):', 'artisanpack-visual-editor' ) );
                if ( ! setName ) {
                    return;
                }
                const iconName = prompt( __( 'Icon name (e.g. github):', 'artisanpack-visual-editor' ) );
                if ( ! iconName ) {
                    return;
                }
                setAttributes( { iconRef: { set: setName, name: iconName } } );
            } }
            aria-label={ __( 'Choose an icon', 'artisanpack-visual-editor' ) }
        >
            <span className="wp-block-artisanpack-icon__placeholder" aria-hidden="true" />
        </button>
    );

    let body: ReactElement = placeholder;

    if ( normalized.customSvg.trim().length > 0 ) {
        // Custom SVG is dangerously-set only after Phase 2's sanitizer
        // runs server-side. In the editor we still render it raw because
        // it never leaves the author's own browser session before save.
        body = (
            <span
                className="wp-block-artisanpack-icon__svg"
                style={ { ...sizedStyle, ...innerStyle } }
                dangerouslySetInnerHTML={ { __html: normalized.customSvg } }
            />
        );
    } else if ( normalized.iconRef ) {
        body = (
            <span
                className="wp-block-artisanpack-icon__ref"
                style={ { ...sizedStyle, ...innerStyle } }
                aria-hidden={ normalized.isDecorative ? 'true' : undefined }
                title={ normalized.titleAttr || undefined }
            >
                { /* The real SVG arrives once the picker (Phase 4) wires
                     a fetcher; for Phase 1 we render a labeled chip so
                     authors can see the iconRef round-tripped. */ }
                <code>
                    { normalized.iconRef.set }:{ normalized.iconRef.name }
                </code>
            </span>
        );
    }

    const conflict = hasDecorativeLinkConflict( normalized );
    const wrapped = shouldRenderLink( normalized ) ? (
        <a
            href={ normalized.link }
            target={ normalizeLinkTarget( normalized.linkTarget ) || undefined }
            rel={ composeRel( normalized.linkTarget, normalized.linkRel ) || undefined }
            onClick={ ( event ) => event.preventDefault() }
        >
            { body }
        </a>
    ) : (
        body
    );

    return (
        <div { ...blockProps }>
            { wrapped }
            { conflict && (
                <span
                    role="alert"
                    className="wp-block-artisanpack-icon__a11y-warning"
                >
                    { __(
                        'A decorative icon inside a link needs an aria-label so screen readers can announce the destination.',
                        'artisanpack-visual-editor'
                    ) }
                </span>
            ) }
        </div>
    );
}
