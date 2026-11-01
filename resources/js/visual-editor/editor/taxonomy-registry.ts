/**
 * Taxonomy registry — reads the host-registered taxonomies stamped onto
 * the editor mount element as the `data-taxonomies` JSON attribute (set
 * by {@see ArtisanPackUI\VisualEditor\Resources\TaxonomyRegistry} via the
 * `<x-visual-editor>` component and the site-editor shell).
 *
 * Consumers (notably `artisanpack/post-terms`) use this list to register
 * one block variation per taxonomy — so "Categories", "Tags", and any
 * custom taxonomy each get an inserter tile — and to populate the
 * taxonomy picker in the block's Settings sidebar, letting authors switch
 * taxonomy without hand-editing markup. The Query Loop's Filters panel
 * reads the same list through {@see getTaxonomiesForPostType}.
 *
 * Both the post editor (`[data-ap-visual-editor]`) and the site editor
 * (`[data-ap-site-editor]`) stamp the attribute, so the registry reads
 * whichever mount is present — templates that host `post-terms` live in
 * the site editor.
 *
 * The list is resolved at module-import time. Subsequent calls return
 * the cached snapshot — if the editor mounts after this module has been
 * imported (e.g. tests), {@see refreshTaxonomies} can be called to
 * re-read the DOM.
 *
 * @since 1.9.0
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

export interface TaxonomyDescriptor {
    readonly slug: string;
    readonly label: string;
    readonly plural: string;
    /** Post types the taxonomy applies to, when the host declares them. */
    readonly postTypes?: ReadonlyArray<string>;
}

/**
 * WordPress-core's built-in public taxonomies. Used when the mount
 * attribute is missing so the inserter always surfaces the two common
 * taxonomies even in dev environments where the attribute hasn't been
 * wired up yet. Built on each call rather than at import time so the
 * labels pick up translations loaded after this module.
 */
function fallbackTaxonomies(): ReadonlyArray<TaxonomyDescriptor> {
    return Object.freeze([
        Object.freeze({ slug: 'category', label: __('Category', TEXT_DOMAIN), plural: __('Categories', TEXT_DOMAIN) }),
        Object.freeze({ slug: 'post_tag', label: __('Tag', TEXT_DOMAIN), plural: __('Tags', TEXT_DOMAIN) }),
    ]);
}

// A slug lands verbatim in the block's `term` attribute and the variation
// name (`term-${slug}`), so constrain it to a safe identifier set —
// mirroring the PHP registry's guard.
const SAFE_SLUG_PATTERN = /^[a-z0-9_-]+$/;

// Mirrors the PHP registry's `SAFE_POST_TYPE_PATTERN`.
const SAFE_POST_TYPE_PATTERN = /^[A-Za-z0-9_-]{1,64}$/;

// The built-ins' terms always come from cms-framework's post tables, and
// those ids mean nothing to another post type's query, so a host's
// `postTypes` can't widen them.
const BUILT_IN_POST_TYPES: ReadonlyMap<string, ReadonlyArray<string>> = new Map([
    ['category', ['post']],
    ['post_tag', ['post']],
]);

// `undefined` = not read yet; `null` = read, nothing usable stamped.
let cached: ReadonlyArray<TaxonomyDescriptor> | null | undefined;

function parseFromElement(
    element: Element | null
): ReadonlyArray<TaxonomyDescriptor> | null {
    if (element === null || !(element instanceof HTMLElement)) {
        return null;
    }

    const raw = element.dataset.taxonomies?.trim();
    if (!raw) {
        return null;
    }

    try {
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return null;
        }

        const result: TaxonomyDescriptor[] = [];
        for (const entry of parsed) {
            if (
                entry === null ||
                typeof entry !== 'object' ||
                typeof (entry as { slug?: unknown }).slug !== 'string' ||
                typeof (entry as { label?: unknown }).label !== 'string'
            ) {
                continue;
            }

            const slug = (entry as { slug: string }).slug.trim().toLowerCase();
            const label = (entry as { label: string }).label.trim();

            if (slug === '' || !SAFE_SLUG_PATTERN.test(slug) || label === '') {
                continue;
            }

            const rawPlural = (entry as { plural?: unknown }).plural;
            const plural =
                typeof rawPlural === 'string' && rawPlural.trim() !== ''
                    ? rawPlural.trim()
                    : label;

            const rawPostTypes = (entry as { postTypes?: unknown }).postTypes;
            const postTypes = Array.isArray(rawPostTypes)
                ? rawPostTypes.filter(
                      (postType): postType is string =>
                          typeof postType === 'string' && SAFE_POST_TYPE_PATTERN.test(postType)
                  )
                : [];

            result.push(
                Object.freeze({
                    slug,
                    label,
                    plural,
                    ...(postTypes.length > 0 ? { postTypes: Object.freeze(postTypes) } : {}),
                })
            );
        }

        return result.length > 0 ? Object.freeze(result) : null;
    } catch {
        return null;
    }
}

/**
 * Returns the taxonomies stamped onto the mount element. Reads the post
 * editor mount first, then the site editor mount, then falls back to the
 * built-in `category` / `post_tag` defaults.
 *
 * @since 1.9.0
 */
export function getTaxonomies(): ReadonlyArray<TaxonomyDescriptor> {
    if (cached === undefined) {
        cached = typeof document === 'undefined'
            ? null
            : parseFromElement(document.querySelector('[data-ap-visual-editor]')) ??
              parseFromElement(document.querySelector('[data-ap-site-editor]'));
    }

    return cached ?? fallbackTaxonomies();
}

/**
 * Returns the taxonomies that apply to a post type: the built-ins for
 * `post`, custom taxonomies listing it in `postTypes`, and custom
 * taxonomies that declare no post types at all.
 *
 * @since 1.20.0
 */
export function getTaxonomiesForPostType(postType: string): ReadonlyArray<TaxonomyDescriptor> {
    return getTaxonomies().filter((taxonomy) => {
        const postTypes = BUILT_IN_POST_TYPES.get(taxonomy.slug) ?? taxonomy.postTypes;

        return postTypes === undefined || postTypes.includes(postType);
    });
}

/**
 * Test-only: forget the cached snapshot so the next call re-reads the
 * DOM. Used by Vitest suites that swap the mount markup between cases.
 *
 * @internal
 */
export function refreshTaxonomies(): void {
    cached = undefined;
}
