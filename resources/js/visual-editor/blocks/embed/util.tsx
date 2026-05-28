/**
 * Embed — utility helpers.
 *
 * Ported from `@wordpress/block-library/src/embed/util.js` (v9.43.0).
 * Behavioural parity with upstream is preserved, with two intentional
 * divergences:
 *
 *   - `createUpgradedEmbedBlock` returns `createBlock('artisanpack/embed', ...)`
 *     instead of `createBlock('core/embed', ...)` so it remains useful for
 *     future block forks (e.g. audio/video) that want to upgrade an audio
 *     URL into the fork's embed block rather than core's.
 *   - The upstream `kebabCase` import from `@wordpress/components` private
 *     APIs is replaced with a small local implementation so the fork does
 *     not depend on block-library internals not exposed via the package's
 *     `exports` field.
 */

import clsx from 'clsx';
import memoize from 'memize';
import { renderToString } from '@wordpress/element';
import {
    createBlock,
    getBlockType,
    getBlockVariations,
} from '@wordpress/blocks';

import metadata from './block.json';
import { ASPECT_RATIOS, WP_EMBED_TYPE } from './constants';

const { name: DEFAULT_EMBED_BLOCK } = metadata;

interface EmbedAttributes {
    readonly url?: string;
    readonly providerNameSlug?: string;
    readonly type?: string;
    readonly className?: string;
    readonly [key: string]: unknown;
}

interface EmbedPreviewData {
    readonly html?: string | false;
    readonly type?: string;
    readonly provider_name?: string;
    readonly [key: string]: unknown;
}

interface BlockVariation {
    readonly name?: string;
    readonly patterns?: readonly RegExp[];
    readonly attributes?: EmbedAttributes;
}

interface UpgradeProps {
    readonly preview?: EmbedPreviewData;
    readonly attributes?: EmbedAttributes;
}

/**
 * Returns the embed block's information by matching the provided service
 * provider.
 */
export const getEmbedInfoByProvider = (
    provider: string | undefined
): BlockVariation | undefined =>
    (getBlockVariations(DEFAULT_EMBED_BLOCK) as BlockVariation[] | undefined)?.find(
        ({ name }) => name === provider
    );

/**
 * Returns true if any of the regular expressions match the URL.
 */
export const matchesPatterns = (
    url: string,
    patterns: readonly RegExp[] = []
): boolean => patterns.some((pattern) => url.match(pattern));

/**
 * Finds the block variation that should be used for the URL,
 * based on the provided URL and the variation's patterns.
 */
export const findMoreSuitableBlock = (
    url: string
): BlockVariation | undefined =>
    (getBlockVariations(DEFAULT_EMBED_BLOCK) as BlockVariation[] | undefined)?.find(
        ({ patterns }) => matchesPatterns(url, patterns)
    );

export const isFromWordPress = (html: string | false | undefined): boolean =>
    !!html && html.includes('class="wp-embedded-content"');

interface PhotoPreview {
    readonly url?: string;
    readonly thumbnail_url?: string;
    readonly title?: string;
}

export const getPhotoHtml = (photo: PhotoPreview): string => {
    // If full image url not found use thumbnail.
    const imageUrl = photo.url || photo.thumbnail_url;

    // 100% width for the preview so it fits nicely into the document, some
    // "thumbnails" are actually the full size photo.
    const photoPreview = (
        <p>
            <img src={imageUrl} alt={photo.title} width="100%" />
        </p>
    );
    return renderToString(photoPreview);
};

/**
 * Creates a more suitable embed block based on the passed in props
 * and attributes generated from an embed block's preview.
 *
 * Diverges from upstream by returning `artisanpack/embed` instead of
 * `core/embed`.
 */
export const createUpgradedEmbedBlock = (
    props: UpgradeProps,
    attributesFromPreview: EmbedAttributes = {}
): ReturnType<typeof createBlock> | undefined => {
    const { preview, attributes = {} } = props;
    const { url, providerNameSlug, type, ...restAttributes } = attributes;

    if (!url || !getBlockType(DEFAULT_EMBED_BLOCK)) {
        return undefined;
    }

    const matchedBlock = findMoreSuitableBlock(url);

    // WordPress blocks can work on multiple sites, and so don't have patterns,
    // so if we're in a WordPress block, assume the user has chosen it for a
    // WordPress URL.
    const isCurrentBlockWP =
        providerNameSlug === 'wordpress' || type === WP_EMBED_TYPE;
    // If current block is not WordPress and a more suitable block found
    // that is different from the current one, create the new matched block.
    const shouldCreateNewBlock =
        !isCurrentBlockWP &&
        matchedBlock &&
        (matchedBlock.attributes?.providerNameSlug !== providerNameSlug ||
            !providerNameSlug);
    if (shouldCreateNewBlock && matchedBlock) {
        return createBlock(DEFAULT_EMBED_BLOCK, {
            url,
            ...restAttributes,
            ...(matchedBlock.attributes ?? {}),
        });
    }

    const wpVariation = (
        getBlockVariations(DEFAULT_EMBED_BLOCK) as BlockVariation[] | undefined
    )?.find(({ name }) => name === 'wordpress');

    // We can't match the URL for WordPress embeds, we have to check the HTML
    // instead.
    if (
        !wpVariation ||
        !preview ||
        !isFromWordPress(preview.html) ||
        isCurrentBlockWP
    ) {
        return undefined;
    }

    // This is not the WordPress embed block so transform it into one.
    return createBlock(DEFAULT_EMBED_BLOCK, {
        url,
        ...(wpVariation.attributes ?? {}),
        // By now we have the preview, but when the new block first renders,
        // it won't have had all the attributes set, and so won't get the
        // correct type and it won't render correctly. So, we pass through the
        // current attributes here so that the initial render works when we
        // switch to the WordPress block.
        ...attributesFromPreview,
    });
};

/**
 * Determine if the block already has an aspect ratio class applied.
 */
export const hasAspectRatioClass = (
    existingClassNames: string | undefined
): boolean => {
    if (!existingClassNames) {
        return false;
    }
    return ASPECT_RATIOS.some(({ className }) =>
        existingClassNames.includes(className)
    );
};

/**
 * Removes all previously set aspect ratio related classes and returns the
 * remaining class names.
 */
export const removeAspectRatioClasses = (
    existingClassNames: string | undefined
): string | undefined => {
    if (!existingClassNames) {
        // Avoid extraneous work and also, by returning the same value as
        // received, ensure the post is not dirtied by a change of the block
        // attribute from `undefined` to an empty string.
        return existingClassNames;
    }
    const aspectRatioClassNames = ASPECT_RATIOS.reduce<string[]>(
        (accumulator, { className }) => {
            accumulator.push(className);
            return accumulator;
        },
        ['wp-has-aspect-ratio']
    );
    let outputClassNames = existingClassNames;
    for (const className of aspectRatioClassNames) {
        outputClassNames = outputClassNames.split(className).join('');
    }
    return outputClassNames.replace(/\s+/g, ' ').trim();
};

/**
 * Checks if HTML already contains responsive aspect ratio styling.
 */
export function hasInlineResponsivePadding(html: string): boolean {
    const paddingPattern = /padding-(top|bottom)\s*:\s*[\d.]+%/i;
    return paddingPattern.test(html);
}

/**
 * Returns class names with any relevant responsive aspect ratio names.
 */
export function getClassNames(
    html: string,
    existingClassNames: string | undefined,
    allowResponsive: boolean = true
): string | undefined {
    if (!allowResponsive) {
        return removeAspectRatioClasses(existingClassNames);
    }

    // If the embed HTML already contains responsive wrapper styling (like
    // Flickr), don't add our own aspect ratio classes to avoid double padding.
    if (hasInlineResponsivePadding(html)) {
        return removeAspectRatioClasses(existingClassNames);
    }

    const previewDocument = document.implementation.createHTMLDocument('');
    previewDocument.body.innerHTML = html;
    const iframe = previewDocument.body.querySelector('iframe');

    // If we have a fixed aspect iframe, and it's a responsive embed block.
    if (iframe && iframe.height && iframe.width) {
        const aspectRatio = (
            Number(iframe.width) / Number(iframe.height)
        ).toFixed(2);
        for (
            let ratioIndex = 0;
            ratioIndex < ASPECT_RATIOS.length;
            ratioIndex++
        ) {
            const potentialRatio = ASPECT_RATIOS[ratioIndex];
            if (Number(aspectRatio) >= Number(potentialRatio.ratio)) {
                const ratioDiff =
                    Number(aspectRatio) - Number(potentialRatio.ratio);
                if (ratioDiff > 0.1) {
                    return removeAspectRatioClasses(existingClassNames);
                }
                return clsx(
                    removeAspectRatioClasses(existingClassNames),
                    potentialRatio.className,
                    'wp-has-aspect-ratio'
                );
            }
        }
    }

    return existingClassNames;
}

/**
 * Fallback behaviour for unembeddable URLs.
 * Creates a paragraph block containing a link to the URL, and calls
 * `onReplace`.
 */
export function fallback(
    url: string,
    onReplace: (block: ReturnType<typeof createBlock>) => void
): void {
    const link = <a href={url}>{url}</a>;
    onReplace(
        createBlock('core/paragraph', { content: renderToString(link) })
    );
}

/**
 * Tiny kebab-case helper. Replaces the upstream `kebabCase` private API
 * import so the fork does not depend on block-library internals.
 */
function kebabCase(value: string): string {
    return value
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/[\s_]+/g, '-')
        .toLowerCase();
}

/**
 * Gets block attributes based on the preview and responsive state.
 */
export const getAttributesFromPreview = memoize(
    (
        preview: EmbedPreviewData | undefined,
        title: string,
        currentClassNames: string | undefined,
        isResponsive: boolean,
        allowResponsive: boolean = true
    ): EmbedAttributes => {
        if (!preview) {
            return {};
        }

        const attributes: EmbedAttributes = {};
        let { type = 'rich' } = preview;
        const { html, provider_name: providerName } = preview;
        const providerNameSlug = kebabCase(
            (providerName || title).toLowerCase()
        );

        if (isFromWordPress(html)) {
            type = WP_EMBED_TYPE;
        }

        if (html || type === 'photo') {
            (attributes as { type?: string }).type = type;
            (attributes as { providerNameSlug?: string }).providerNameSlug =
                providerNameSlug;
        }

        if (hasAspectRatioClass(currentClassNames)) {
            return attributes;
        }

        (attributes as { className?: string }).className = getClassNames(
            typeof html === 'string' ? html : '',
            currentClassNames,
            isResponsive && allowResponsive
        );

        return attributes;
    }
);

/**
 * Returns the attributes derived from the preview, merged with the current
 * attributes.
 */
export const getMergedAttributesWithPreview = (
    currentAttributes: EmbedAttributes,
    preview: EmbedPreviewData | undefined,
    title: string,
    isResponsive: boolean
): EmbedAttributes => {
    const { allowResponsive, className } = currentAttributes as {
        allowResponsive?: boolean;
        className?: string;
    };

    return {
        ...currentAttributes,
        ...getAttributesFromPreview(
            preview,
            title,
            className,
            isResponsive,
            allowResponsive ?? true
        ),
    };
};
