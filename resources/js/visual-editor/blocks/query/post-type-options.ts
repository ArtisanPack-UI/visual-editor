/**
 * Query Loop — "Post type" picker options (#847).
 *
 * Builds the option list for the inspector's Post type select:
 *
 *   1. Base list — the server-resolved options stamped onto the editor
 *      mount as `data-query-post-types` (set by
 *      {@see ArtisanPackUI\VisualEditor\Resources\QueryPostTypeRegistry}
 *      via the PHP `ap.visualEditor.query.postTypes` filter), falling
 *      back to Posts / Pages when the attribute is missing.
 *   2. The JS `ap.visualEditor.query.postTypes` filter runs over that
 *      list so hosts with an editor bundle can add, relabel, or remove
 *      options client-side.
 *   3. A saved `postType` missing from the result is appended as its own
 *      option, so opening a block saved against a renamed / removed type
 *      doesn't silently flip it to `post`.
 *
 * Both the post editor (`[data-ap-visual-editor]`) and the site editor
 * (`[data-ap-site-editor]`) stamp the attribute; the post editor mount
 * wins when both are present.
 *
 * @since 1.14.0
 */

import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

export const QUERY_POST_TYPES_FILTER = 'ap.visualEditor.query.postTypes';

export interface QueryPostTypeOption {
    label: string;
    value: string;
}

// Mirrors `QueryPostTypeRegistry::SAFE_VALUE_PATTERN` on the PHP side.
// The 64-char cap matches `QueryResolveRequest`'s `postType` rule so a
// listed type can always be previewed.
const SAFE_VALUE_PATTERN = /^[A-Za-z0-9_-]{1,64}$/;

let cachedServerOptions: QueryPostTypeOption[] | null | undefined;

function getDefaultOptions(): QueryPostTypeOption[] {
    return [
        { label: __( 'Posts', TEXT_DOMAIN ), value: 'post' },
        { label: __( 'Pages', TEXT_DOMAIN ), value: 'page' },
    ];
}

/**
 * Keep entries with a non-empty string label and a safe value, trimming
 * both. Duplicate values keep the first entry.
 */
function normalise( entries: unknown[] ): QueryPostTypeOption[] {
    const options: QueryPostTypeOption[] = [];
    const seen = new Set<string>();

    for ( const entry of entries ) {
        if ( entry === null || typeof entry !== 'object' ) {
            continue;
        }

        const { label, value } = entry as { label?: unknown; value?: unknown };
        if ( typeof label !== 'string' || typeof value !== 'string' ) {
            continue;
        }

        const trimmedLabel = label.trim();
        const trimmedValue = value.trim();
        if (
            trimmedLabel === '' ||
            ! SAFE_VALUE_PATTERN.test( trimmedValue ) ||
            seen.has( trimmedValue )
        ) {
            continue;
        }

        seen.add( trimmedValue );
        options.push( { label: trimmedLabel, value: trimmedValue } );
    }

    return options;
}

function parseFromElement( element: Element | null ): QueryPostTypeOption[] | null {
    if ( element === null || ! ( element instanceof HTMLElement ) ) {
        return null;
    }

    const raw = element.dataset.queryPostTypes?.trim();
    if ( ! raw ) {
        return null;
    }

    try {
        const parsed: unknown = JSON.parse( raw );
        if ( ! Array.isArray( parsed ) ) {
            return null;
        }

        const options = normalise( parsed );
        return options.length > 0 ? options : null;
    } catch {
        return null;
    }
}

function getServerOptions(): QueryPostTypeOption[] | null {
    if ( cachedServerOptions !== undefined ) {
        return cachedServerOptions;
    }

    if ( typeof document === 'undefined' ) {
        cachedServerOptions = null;
        return cachedServerOptions;
    }

    cachedServerOptions =
        parseFromElement( document.querySelector( '[data-ap-visual-editor]' ) ) ??
        parseFromElement( document.querySelector( '[data-ap-site-editor]' ) );

    return cachedServerOptions;
}

/**
 * Returns the Post type picker options for a Query Loop whose saved
 * `postType` is `currentPostType`.
 *
 * A filter callback that throws is logged and skipped (the base list is
 * used), and a filter result with no valid entries also falls back to
 * the base list, so the picker is never empty.
 *
 * @since 1.14.0
 */
export function getQueryPostTypeOptions( currentPostType: string ): QueryPostTypeOption[] {
    const base = getServerOptions() ?? getDefaultOptions();
    let options = base;

    try {
        const filtered: unknown = applyFilters(
            QUERY_POST_TYPES_FILTER,
            base.map( ( option ) => ( { ...option } ) )
        );

        if ( Array.isArray( filtered ) ) {
            const normalised = normalise( filtered );
            if ( normalised.length > 0 ) {
                options = normalised;
            }
        }
    } catch ( error ) {
        // eslint-disable-next-line no-console
        console.error(
            '[artisanpack-ui/visual-editor] A ' +
                `"${ QUERY_POST_TYPES_FILTER }" filter callback threw. ` +
                'Using the unfiltered post type list.',
            error
        );
    }

    if ( currentPostType !== '' && ! options.some( ( option ) => option.value === currentPostType ) ) {
        options = [
            ...options,
            {
                /* translators: %s: post type slug saved on the block that is not in the picker's list. */
                label: sprintf( __( '%s (unavailable)', TEXT_DOMAIN ), currentPostType ),
                value: currentPostType,
            },
        ];
    }

    return options;
}

/**
 * Test-only: forget the cached server options so the next call re-reads
 * the DOM.
 *
 * @internal
 */
export function refreshQueryPostTypeOptions(): void {
    cachedServerOptions = undefined;
}
