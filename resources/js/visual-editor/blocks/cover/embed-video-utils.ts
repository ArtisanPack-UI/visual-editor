/**
 * Cover — embed-video utilities.
 *
 * Ported from `@wordpress/block-library/src/cover/embed-video-utils.js`
 * (v9.43.0). The dependency on `../embed/util.matchesPatterns` is replaced
 * by an inline `matchesPatterns` helper so the fork does not depend on
 * block-library internals that aren't exposed via the package's `exports`.
 */

import { getBlockVariations } from '@wordpress/blocks';

const DEFAULT_EMBED_BLOCK = 'core/embed';

const VIDEO_PROVIDERS = [
    'youtube',
    'vimeo',
    'videopress',
    'animoto',
    'tiktok',
    'wordpress-tv',
];

interface BlockVariation {
    name: string;
    patterns?: readonly RegExp[];
}

/**
 * Inline replacement for `@wordpress/block-library/src/embed/util.matchesPatterns`.
 */
function matchesPatterns(
    url: string,
    patterns: readonly RegExp[] = []
): boolean {
    return patterns.some((pattern) => url.match(pattern));
}

function findVideoEmbedProvider(url: string): BlockVariation | null {
    const embedVariations = getBlockVariations(DEFAULT_EMBED_BLOCK) as
        | BlockVariation[]
        | undefined;

    if (!embedVariations) {
        return null;
    }

    const matchingVariation = embedVariations.find(({ patterns }) =>
        matchesPatterns(url, patterns)
    );

    if (
        !matchingVariation ||
        !VIDEO_PROVIDERS.includes(matchingVariation.name)
    ) {
        return null;
    }

    return matchingVariation;
}

export function isValidVideoEmbedUrl(url: string | undefined): boolean {
    if (!url) {
        return false;
    }
    return findVideoEmbedProvider(url) !== null;
}

export function getVideoEmbedProvider(url: string | undefined): string | null {
    if (!url) {
        return null;
    }
    const embedBlock = findVideoEmbedProvider(url);
    return embedBlock ? embedBlock.name : null;
}

export function getIframeSrc(html: string | undefined): string | null {
    if (!html) {
        return null;
    }

    const srcMatch = html.match(/src=["']([^"']+)["']/);
    return srcMatch ? srcMatch[1] : null;
}

export function detectProviderFromSrc(
    src: string | undefined
): string | null {
    if (!src) {
        return null;
    }

    const lowerSrc = src.toLowerCase();

    if (
        lowerSrc.includes('youtube.com') ||
        lowerSrc.includes('youtu.be')
    ) {
        return 'youtube';
    }
    if (lowerSrc.includes('vimeo.com')) {
        return 'vimeo';
    }
    if (lowerSrc.includes('videopress.com')) {
        return 'videopress';
    }
    if (lowerSrc.includes('animoto.com')) {
        return 'animoto';
    }
    if (lowerSrc.includes('tiktok.com')) {
        return 'tiktok';
    }
    if (lowerSrc.includes('wordpress.tv')) {
        return 'wordpress-tv';
    }

    return null;
}

export function getBackgroundVideoSrc(src: string | undefined): string {
    if (!src) {
        return src ?? '';
    }

    try {
        const url = new URL(src);
        const provider = detectProviderFromSrc(src);

        switch (provider) {
            case 'youtube': {
                url.searchParams.set('autoplay', '1');
                url.searchParams.set('mute', '1');
                url.searchParams.set('loop', '1');
                url.searchParams.set('controls', '0');
                url.searchParams.set('showinfo', '0');
                url.searchParams.set('modestbranding', '1');
                url.searchParams.set('playsinline', '1');
                url.searchParams.set('rel', '0');
                const videoId = url.pathname.split('/').pop();
                if (videoId) {
                    url.searchParams.set('playlist', videoId);
                }
                break;
            }

            case 'vimeo':
                url.searchParams.set('autoplay', '1');
                url.searchParams.set('muted', '1');
                url.searchParams.set('loop', '1');
                url.searchParams.set('background', '1');
                url.searchParams.set('controls', '0');
                break;

            case 'videopress':
            case 'wordpress-tv':
                url.searchParams.set('autoplay', '1');
                url.searchParams.set('loop', '1');
                url.searchParams.set('muted', '1');
                break;

            default:
                url.searchParams.set('autoplay', '1');
                url.searchParams.set('muted', '1');
                url.searchParams.set('loop', '1');
                break;
        }

        return url.toString();
    } catch {
        return src;
    }
}
