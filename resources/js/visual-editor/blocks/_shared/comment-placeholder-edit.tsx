/**
 * Lightweight editor preview for server-rendered comment display blocks.
 *
 * The Phase #519 comments-family forks (`comment-*` inner blocks) live
 * inside `comment-template`, which propagates the resolved comment via
 * the `artisanpack/commentPreview` block context. The real markup is
 * produced server-side by the Blade / React / Vue renderers from
 * stamped `_resolved*` attributes; this preview is only the canvas
 * placeholder.
 *
 * Resolution priority, highest first:
 *
 *  1. The stamped `_resolved*` attribute (front-end / saved-tree path).
 *  2. The `artisanpack/commentPreview` block context that
 *     `comment-template` pipes down per iteration.
 *  3. A clearly-labelled placeholder.
 *
 * Mirrors the post-family `createEntityPlaceholderEdit` helper but
 * scoped to the comment context — comments are not exposed through
 * the editor's `@wordpress/core-data` shim, so there is no live
 * entity-fetch path.
 */

import type { CSSProperties, ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

export type CommentPreviewKind = 'text' | 'html' | 'image';

/**
 * Resolved comment values piped down through the
 * `artisanpack/commentPreview` block context. Mirrors the
 * server-side `CommentResolver` output shape.
 */
export interface CommentPreview {
    readonly id?: number | string;
    readonly authorName?: string;
    readonly authorUrl?: string;
    readonly authorAvatarUrl?: string;
    readonly content?: string;
    readonly date?: string;
    readonly dateFormatted?: string;
    readonly editLink?: string;
    readonly replyLink?: string;
}

export interface CommentPreviewValue {
    readonly text?: string;
    readonly imageUrl?: string;
    readonly imageAlt?: string;
}

export interface CommentPlaceholderConfig {
    readonly label: string;
    readonly resolvedKey: string;
    readonly kind: CommentPreviewKind;
    readonly altKey?: string;
    readonly fromCommentPreview?: (
        comment: CommentPreview
    ) => CommentPreviewValue | null;
    /**
     * Optional hook for `image` kind blocks that need to project block
     * attributes onto the rendered `<img>` (e.g. comment-author-avatar's
     * `width` / `height`). Returned props are spread before the helper's
     * default `style` so the helper's `maxWidth: 100%` cap still wins
     * when no explicit sizing is supplied.
     */
    readonly getImageProps?: (
        attributes: Record<string, unknown>
    ) => Record<string, unknown>;
}

const COMMENT_PREVIEW_CONTEXT_KEY = 'artisanpack/commentPreview';

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

function htmlToText( html: string ): string {
    if ( typeof DOMParser === 'undefined' ) {
        return html.replace( /<[^>]*>/g, '' );
    }
    return (
        new DOMParser().parseFromString( html, 'text/html' ).body
            .textContent ?? ''
    );
}

function readCommentPreview( context: unknown ): CommentPreview | null {
    if ( context === null || typeof context !== 'object' ) {
        return null;
    }
    const value = ( context as Record<string, unknown> )[ COMMENT_PREVIEW_CONTEXT_KEY ];
    if ( value === null || typeof value !== 'object' ) {
        return null;
    }
    return value as CommentPreview;
}

function readResolvedAttributes(
    config: CommentPlaceholderConfig,
    attributes: AnyProps
): CommentPreviewValue {
    const resolved = asString( attributes?.[ config.resolvedKey ] );

    if ( config.kind === 'image' ) {
        const alt = config.altKey ? asString( attributes?.[ config.altKey ] ) : '';
        return { imageUrl: resolved, imageAlt: alt };
    }

    return { text: resolved };
}

function hasPreviewValue( kind: CommentPreviewKind, value: CommentPreviewValue ): boolean {
    if ( kind === 'image' ) {
        return typeof value.imageUrl === 'string' && value.imageUrl !== '';
    }
    return typeof value.text === 'string' && value.text !== '';
}

function renderResolved(
    config: CommentPlaceholderConfig,
    value: CommentPreviewValue,
    blockProps: AnyProps,
    attributes: AnyProps
): ReactElement {
    const { kind } = config;

    if ( kind === 'html' ) {
        return <div { ...blockProps }>{ htmlToText( value.text ?? '' ) }</div>;
    }
    if ( kind === 'image' ) {
        const imageProps = config.getImageProps?.( attributes ) ?? {};
        return (
            <div { ...blockProps }>
                { /* eslint-disable-next-line jsx-a11y/alt-text */ }
                <img
                    src={ value.imageUrl ?? '' }
                    alt={ value.imageAlt ?? '' }
                    { ...imageProps }
                    style={ { maxWidth: '100%' } }
                />
            </div>
        );
    }
    return <div { ...blockProps }>{ value.text ?? '' }</div>;
}

export function createCommentPlaceholderEdit(
    config: CommentPlaceholderConfig
): ( props: AnyProps ) => ReactElement {
    function CommentPlaceholderEdit( props: AnyProps ): ReactElement {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = ( useBlockProps as any )();
        const attributes = props.attributes ?? {};

        const resolvedValue = readResolvedAttributes( config, attributes );
        if ( hasPreviewValue( config.kind, resolvedValue ) ) {
            return renderResolved( config, resolvedValue, blockProps, attributes );
        }

        if ( config.fromCommentPreview !== undefined ) {
            const comment = readCommentPreview( props.context );
            if ( comment !== null ) {
                const fromContext = config.fromCommentPreview( comment );
                if (
                    fromContext !== null &&
                    hasPreviewValue( config.kind, fromContext )
                ) {
                    return renderResolved( config, fromContext, blockProps, attributes );
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

    CommentPlaceholderEdit.displayName = `CommentPlaceholderEdit(${ config.label })`;
    return CommentPlaceholderEdit;
}

export default createCommentPlaceholderEdit;
