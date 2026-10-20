/**
 * Template slug → previewed post type (#993).
 *
 * The site editor previews a single-entry template's `post-*` blocks
 * with real content from an entry of the post type the template
 * renders. Sits next to `fallback-chain.ts`, which owns the rest of the
 * client-side slug logic, and follows the same hierarchy:
 *
 *  - `single`, `singular`, `single-post` → `post`
 *  - `single-post-{slug}` → `post`, preferring the entry with that slug
 *  - `single-{type}` → `{type}` (a custom content type)
 *  - `single-{type}-{slug}` → `{type}`, preferring the entry with that
 *    slug, when `{type}` is a registered content type
 *  - `page` → `page`
 *  - `page-{slug}` → `page`, preferring the page with that slug
 *  - anything else (`index`, `archive`, `home`, `404`, custom) → null:
 *    the template isn't tied to a single entry, so the blocks keep their
 *    placeholders.
 */

import { getContentTypes } from '../editor/content-type-registry';

export interface TemplatePreviewTarget {
    /** Post type the preview entry is drawn from. */
    readonly postType: string;
    /**
     * Entry slug the template is specific to (`page-about` → `about`),
     * preferred over the latest entry when it is among the results.
     */
    readonly entrySlug: string | null;
}

const SAFE_POST_TYPE_PATTERN = /^[a-z0-9_-]{1,64}$/;

function target(postType: string, entrySlug: string | null = null): TemplatePreviewTarget | null {
    return SAFE_POST_TYPE_PATTERN.test(postType) ? { postType, entrySlug } : null;
}

/**
 * Maps a template slug to the post type (and optional entry slug) its
 * `post-*` blocks preview. Returns null for templates that don't render
 * a single entry.
 *
 * `knownPostTypes` (default: the host's registered content types) lets
 * `single-{type}-{slug}` resolve to `{type}` — the longest registered
 * type that prefixes the remainder wins, so a hyphenated type such as
 * `case-study` isn't split. Unregistered remainders fall back to being
 * read as the type itself.
 *
 * @since 1.15.3
 */
export function templateSlugToPostType(
    slug: string,
    knownPostTypes: readonly string[] = getContentTypes().map((type) => type.slug)
): TemplatePreviewTarget | null {
    const normalized = slug.trim().toLowerCase();

    if (normalized === 'single' || normalized === 'singular' || normalized === 'single-post') {
        return target('post');
    }

    if (normalized === 'page') {
        return target('page');
    }

    const pageMatch = /^page-(.+)$/.exec(normalized);

    if (pageMatch !== null && pageMatch[1] !== undefined) {
        return target('page', pageMatch[1]);
    }

    const postSlugMatch = /^single-post-(.+)$/.exec(normalized);

    if (postSlugMatch !== null && postSlugMatch[1] !== undefined) {
        return target('post', postSlugMatch[1]);
    }

    const typeMatch = /^single-(.+)$/.exec(normalized);

    if (typeMatch !== null && typeMatch[1] !== undefined) {
        const rest = typeMatch[1];
        const known = [...knownPostTypes]
            .map((type) => type.toLowerCase())
            .filter((type) => rest === type || rest.startsWith(`${type}-`))
            .sort((a, b) => b.length - a.length)[0];

        if (known !== undefined) {
            return target(known, rest === known ? null : rest.slice(known.length + 1));
        }

        return target(rest);
    }

    return null;
}
