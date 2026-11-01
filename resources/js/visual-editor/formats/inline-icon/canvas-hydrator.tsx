/**
 * Real glyphs for placeholder inline icons in the editor canvas (#1039).
 *
 * A registered-set icon saved with {@link SET_ICON_PLACEHOLDER_SVG} has
 * no glyph until the server hydrator resolves it at render, so it's
 * invisible while editing. The format's `edit` component can't help:
 * it only mounts for the selected rich-text field.
 *
 * This watches the canvas instead and fills each placeholder's DOM from
 * its `data-icon-set` / `data-icon-name` reference. It never touches
 * block attributes, so opening a page doesn't dirty it. If an author
 * later edits that field, rich-text reads the glyph back from the DOM
 * and saves it, the same as a freshly inserted icon.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import { useEffect, useRef } from 'react';
import type { ReactElement } from 'react';

import { fetchIconSvg } from '../../blocks/icon/fetch-svg';
import { IN_TREE_CANVAS_ROOT } from '../../support/canvas-scoped-styles';

import { INLINE_ICON_CLASS, INLINE_ICON_SVG_STYLE } from './settings';

const SET_ICON_SELECTOR = `.${ INLINE_ICON_CLASS }[data-icon-set][data-icon-name]`;

/**
 * Whether a set icon's span has no glyph to show.
 *
 * @since 1.20.0
 *
 * @param icon The icon's span.
 */
export function isInlineIconPlaceholder( icon: Element ): boolean {
    const svg = icon.querySelector( 'svg' );

    return null === svg || 0 === svg.childElementCount;
}

/**
 * The sized glyph from a lookup's markup, or `null` unless it's a single
 * SVG with something in it. An empty SVG would still read as a
 * placeholder, so filling it would trigger another scan and repeat
 * forever.
 *
 * Sizes through the DOM rather than {@link normalizeInlineIconSvg}: that
 * rewrites the markup as a string, and this runs for every visitor of
 * the page, on markup no author chose to insert.
 */
function parseGlyph( svg: string ): Element | null {
    const template = document.createElement( 'template' );

    template.innerHTML = svg.trim();

    const glyph = template.content.firstElementChild;

    if (
        1 !== template.content.childElementCount ||
        'svg' !== glyph?.localName ||
        0 === glyph.childElementCount
    ) {
        return null;
    }

    const existing = ( glyph.getAttribute( 'style' ) ?? '' ).trim().replace( /;+$/, '' );

    glyph.removeAttribute( 'width' );
    glyph.removeAttribute( 'height' );
    glyph.setAttribute(
        'style',
        '' === existing ? INLINE_ICON_SVG_STYLE : `${ existing };${ INLINE_ICON_SVG_STYLE }`,
    );

    return glyph;
}

/**
 * Fills every placeholder under `root`, now and as blocks render or
 * rich-text rewrites its DOM. An icon the server can't resolve is
 * skipped until the next mount, so a failing lookup can't repeat on
 * every canvas mutation.
 *
 * @since 1.20.0
 *
 * @param root     Element the block list renders in.
 * @param fetchSvg Resolves an icon reference to its SVG markup.
 *
 * @return Stops watching.
 */
export function watchInlineIconPlaceholders(
    root: Element,
    fetchSvg: ( set: string, name: string ) => Promise< string > = fetchIconSvg,
): () => void {
    const unresolved = new Set< string >();
    const pending    = new WeakSet< Element >();
    let stopped      = false;

    const fill = ( icon: Element, set: string, name: string ): void => {
        pending.add( icon );

        void fetchSvg( set, name ).then( ( svg ) => {
            pending.delete( icon );

            if ( stopped ) {
                return;
            }

            const glyph = parseGlyph( svg );

            if ( null === glyph ) {
                unresolved.add( `${ set }:${ name }` );
                return;
            }

            if ( icon.isConnected && isInlineIconPlaceholder( icon ) ) {
                icon.replaceChildren( icon.ownerDocument.importNode( glyph, true ) );
            }
        } );
    };

    const scan = (): void => {
        for ( const icon of Array.from( root.querySelectorAll( SET_ICON_SELECTOR ) ) ) {
            const set  = icon.getAttribute( 'data-icon-set' ) ?? '';
            const name = icon.getAttribute( 'data-icon-name' ) ?? '';

            if (
                ! pending.has( icon ) &&
                ! unresolved.has( `${ set }:${ name }` ) &&
                isInlineIconPlaceholder( icon )
            ) {
                fill( icon, set, name );
            }
        }
    };

    const observer = new MutationObserver( scan );

    observer.observe( root, { childList: true, subtree: true } );
    scan();

    return () => {
        stopped = true;
        observer.disconnect();
    };
}

/**
 * Hydrates placeholder inline icons for the canvas it renders in.
 * Render it inside the canvas: its hidden marker locates the document,
 * which for the post editor is the iframe's.
 *
 * @since 1.20.0
 */
export function InlineIconCanvasHydrator(): ReactElement {
    const markerRef = useRef< HTMLSpanElement >( null );

    useEffect( () => {
        const marker = markerRef.current;

        if ( null === marker ) {
            return undefined;
        }

        return watchInlineIconPlaceholders(
            marker.closest( IN_TREE_CANVAS_ROOT ) ?? marker.ownerDocument.body,
        );
    }, [] );

    return <span ref={ markerRef } hidden />;
}
