/**
 * Read More — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from the block's `content` attribute and
 * the stamped `_resolvedPermalink` attribute. The fork previews through
 * `createEntityPlaceholderEdit`:
 *
 *  1. The block's `content` attribute, when set — preview the configured
 *     link text so authors get instant feedback while editing it.
 *  2. A localized "Read more" placeholder otherwise.
 *
 * The permalink isn't surfaced in the editor preview (it's a
 * server-resolved value); the canvas shows the styled text shape only.
 *
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import {
    createEntityPlaceholderEdit,
    type EntityPreviewValue,
} from '../_shared/entity-placeholder-edit';

interface ReadMoreAttributes {
    readonly content?: string;
    readonly _resolvedPermalink?: string;
}

const PlaceholderEdit = createEntityPlaceholderEdit( {
    label: 'Read More',
    resolvedKey: 'content',
    kind: 'text',
    dummyValue: { text: 'Read more' },
} );

export default function ReadMoreEdit( props: {
    attributes?: ReadMoreAttributes;
    context?: unknown;
} ): ReturnType<typeof PlaceholderEdit > {
    const attributes = props.attributes ?? {};
    const content = typeof attributes.content === 'string' ? attributes.content : '';

    // Pass through to the placeholder helper. The `resolvedKey: 'content'`
    // wiring above means a non-empty `content` attribute is treated as
    // the resolved value, so the canvas previews exactly what the
    // renderer will emit; an empty `content` falls through to the
    // configured `dummyValue` ("Read more").
    if ( content !== '' ) {
        return PlaceholderEdit( props );
    }

    const synthesizedAttributes: ReadMoreAttributes & EntityPreviewValue = {
        ...attributes,
    };

    return PlaceholderEdit( { ...props, attributes: synthesizedAttributes } );
}
