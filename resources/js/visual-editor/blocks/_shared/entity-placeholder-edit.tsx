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
 * carries `_resolved*` data previews faithfully); otherwise, when the
 * block is inside an `artisanpack/query` loop, it consumes the resolved
 * first post the query block pipes through `BlockContextProvider` under
 * the `artisanpack/postPreview` key (#483) and renders that post's
 * data; otherwise a clean, clearly-labelled placeholder so the block
 * looks intentional in the canvas. `navigation` and `template-part`
 * keep delegating to their upstream edits (see `forked-entity-edit.tsx`)
 * because they drive the V1 interactive editor surfaces that #413 must
 * not regress.
 */

import type { CSSProperties, ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

import type { QueryPreviewPost } from '../../editor/use-query-preview';

export type EntityPreviewKind = 'text' | 'html' | 'image';

/**
 * Resolved values pulled from the `artisanpack/postPreview` block
 * context — the editor-side analogue of the `_resolvedX` attributes
 * the server-side `PostResolver` stamps for the front-end renderers.
 *
 * `text` / `html` are mutually exclusive: a `text`/`html` kind block
 * consumes `text`, an `image` kind block consumes `image`. The shape
 * is open so post-* edits can return whichever the block needs.
 */
export interface EntityPreviewValue {
    /** Plain text or HTML fragment to render. */
    readonly text?: string;
    /** Image source URL (`image` kind blocks only). */
    readonly imageUrl?: string;
    /** Image alt text (`image` kind blocks only). */
    readonly imageAlt?: string;
}

export interface EntityPlaceholderConfig {
    /** Human-readable block label, e.g. "Post Title". */
    readonly label: string;
    /** Attribute key holding the resolved value, e.g. `_resolvedTitle`. */
    readonly resolvedKey: string;
    /** How to render the resolved value. */
    readonly kind: EntityPreviewKind;
    /** Optional second attribute key (e.g. image alt text). */
    readonly altKey?: string;
    /**
     * Optional extractor that pulls the block's value from the
     * `artisanpack/postPreview` block context the query block pipes
     * down (#483). When the block is inside an `artisanpack/query`
     * loop, the resolved first post is available here; otherwise the
     * extractor is skipped and the placeholder renders.
     */
    readonly fromQueryPreview?: ( post: QueryPreviewPost ) => EntityPreviewValue | null;
}

const PREVIEW_CONTEXT_KEY = 'artisanpack/postPreview';

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

function readQueryPreviewPost( context: unknown ): QueryPreviewPost | null {
    if ( context === null || typeof context !== 'object' ) {
        return null;
    }

    const value = ( context as Record<string, unknown> )[ PREVIEW_CONTEXT_KEY ];

    if ( value === null || typeof value !== 'object' ) {
        return null;
    }

    return value as QueryPreviewPost;
}

/**
 * Build an `edit` component for a server-rendered entity display fork.
 */
export function createEntityPlaceholderEdit(
    config: EntityPlaceholderConfig
): ( props: AnyProps ) => ReactElement {
    function EntityPlaceholderEdit( props: AnyProps ): ReactElement {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = ( useBlockProps as any )();
        const attributes = props.attributes ?? {};

        // Priority order: stamped `_resolved*` attribute (front-end /
        // saved-tree path) → `artisanpack/postPreview` block context
        // (editor canvas inside a query loop, #483) → placeholder.
        const resolvedValue: EntityPreviewValue = readResolvedAttributes( config, attributes );

        if ( hasPreviewValue( config.kind, resolvedValue ) ) {
            return renderResolved( config.kind, resolvedValue, blockProps );
        }

        if ( config.fromQueryPreview !== undefined ) {
            const post = readQueryPreviewPost( props.context );

            if ( post !== null ) {
                const fromContext = config.fromQueryPreview( post );

                if ( fromContext !== null && hasPreviewValue( config.kind, fromContext ) ) {
                    return renderResolved( config.kind, fromContext, blockProps );
                }
            }
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

function readResolvedAttributes(
    config: EntityPlaceholderConfig,
    attributes: AnyProps
): EntityPreviewValue {
    const resolved = asString( attributes?.[ config.resolvedKey ] );

    if ( config.kind === 'image' ) {
        const alt = config.altKey ? asString( attributes?.[ config.altKey ] ) : '';
        return { imageUrl: resolved, imageAlt: alt };
    }

    return { text: resolved };
}

function hasPreviewValue( kind: EntityPreviewKind, value: EntityPreviewValue ): boolean {
    if ( kind === 'image' ) {
        return typeof value.imageUrl === 'string' && value.imageUrl !== '';
    }
    return typeof value.text === 'string' && value.text !== '';
}

function renderResolved(
    kind: EntityPreviewKind,
    value: EntityPreviewValue,
    blockProps: AnyProps
): ReactElement {
    if ( kind === 'html' ) {
        // Text-only preview — see `htmlToText`. The server-side
        // renderers emit the full formatted markup on the front end.
        return <div { ...blockProps }>{ htmlToText( value.text ?? '' ) }</div>;
    }

    if ( kind === 'image' ) {
        return (
            <div { ...blockProps }>
                { /* eslint-disable-next-line jsx-a11y/alt-text */ }
                <img
                    src={ value.imageUrl ?? '' }
                    alt={ value.imageAlt ?? '' }
                    style={ { maxWidth: '100%' } }
                />
            </div>
        );
    }

    return <div { ...blockProps }>{ value.text ?? '' }</div>;
}

export default createEntityPlaceholderEdit;
