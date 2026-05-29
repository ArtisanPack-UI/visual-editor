/**
 * Lightweight editor preview for server-rendered entity display blocks.
 *
 * The Phase I5 entity cluster (#413) forks nine *display* entity blocks —
 * the `post-*` and `site-*` families. Their real markup is produced
 * server-side by the Blade / React / Vue renderers from stamped
 * `_resolved*` attributes; the editor's `@wordpress/core-data` shim does
 * not expose the page or site entity to the post editor, so upstream's
 * edit components either render an empty placeholder (`site-*`) or crash
 * (`post-author` unlocks block-editor private APIs and queries the user
 * entity). Delegating to them therefore gives a poor editing experience.
 *
 * Instead each display fork uses this preview: it renders the block's
 * resolved value when the attribute is present (so a block that already
 * carries `_resolved*` data previews faithfully) and otherwise a clean,
 * clearly-labelled placeholder so the block looks intentional in the
 * canvas. `navigation` and `template-part` keep delegating to their
 * upstream edits (see `forked-entity-edit.tsx`) because they drive the
 * V1 interactive editor surfaces that #413 must not regress.
 */

import type { CSSProperties, ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

export type EntityPreviewKind = 'text' | 'html' | 'image';

export interface EntityPlaceholderConfig {
    /** Human-readable block label, e.g. "Post Title". */
    readonly label: string;
    /** Attribute key holding the resolved value, e.g. `_resolvedTitle`. */
    readonly resolvedKey: string;
    /** How to render the resolved value. */
    readonly kind: EntityPreviewKind;
    /** Optional second attribute key (e.g. image alt text). */
    readonly altKey?: string;
}

const placeholderStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.5em',
    minHeight: '1.5em',
    padding: '0.25em 0.6em',
    border: '1px dashed currentColor',
    borderRadius: '4px',
    opacity: 0.55,
    fontStyle: 'italic',
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyProps = Record<string, any>;

function asString( value: unknown ): string {
    return typeof value === 'string' ? value : '';
}

/**
 * Reduce an HTML string to its text content for the editor preview.
 *
 * The placeholder edit is only a canvas preview — the real, fully-formatted
 * markup is produced by the server-side renderers. Rendering the resolved
 * HTML as text (rather than via `dangerouslySetInnerHTML`) keeps the
 * preview dependency-free and removes any HTML-injection surface from the
 * editor. Falls back to a tag-strip when `DOMParser` is unavailable.
 */
function htmlToText( html: string ): string {
    if ( typeof DOMParser === 'undefined' ) {
        return html.replace( /<[^>]*>/g, '' );
    }

    return (
        new DOMParser().parseFromString( html, 'text/html' ).body
            .textContent ?? ''
    );
}

/**
 * Build an `edit` component for a server-rendered entity display fork.
 */
export function createEntityPlaceholderEdit(
    config: EntityPlaceholderConfig
): ( props: AnyProps ) => ReactElement {
    function EntityPlaceholderEdit( { attributes }: AnyProps ): ReactElement {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = ( useBlockProps as any )();
        const resolved = asString( attributes?.[ config.resolvedKey ] );

        if ( resolved !== '' ) {
            if ( config.kind === 'html' ) {
                // Text-only preview — see `htmlToText`. The server-side
                // renderers emit the full formatted markup on the front end.
                return <div { ...blockProps }>{ htmlToText( resolved ) }</div>;
            }

            if ( config.kind === 'image' ) {
                const alt = config.altKey
                    ? asString( attributes?.[ config.altKey ] )
                    : config.label;
                return (
                    <div { ...blockProps }>
                        { /* eslint-disable-next-line jsx-a11y/alt-text */ }
                        <img src={ resolved } alt={ alt } style={ { maxWidth: '100%' } } />
                    </div>
                );
            }

            return <div { ...blockProps }>{ resolved }</div>;
        }

        return (
            <div { ...blockProps }>
                <span style={ placeholderStyle } contentEditable={ false }>
                    { config.label }
                </span>
            </div>
        );
    }

    EntityPlaceholderEdit.displayName = `EntityPlaceholderEdit(${ config.label })`;

    return EntityPlaceholderEdit;
}

export default createEntityPlaceholderEdit;
