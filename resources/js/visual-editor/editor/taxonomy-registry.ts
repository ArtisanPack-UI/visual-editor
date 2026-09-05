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
 * taxonomy without hand-editing markup.
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

export interface TaxonomyDescriptor {
    readonly slug: string;
    readonly label: string;
    readonly plural: string;
}

/**
 * WordPress-core's built-in public taxonomies. Used when the mount
 * attribute is missing so the inserter always surfaces the two common
 * taxonomies even in dev environments where the attribute hasn't been
 * wired up yet.
 */
const FALLBACK_TAXONOMIES: ReadonlyArray<TaxonomyDescriptor> = Object.freeze([
    Object.freeze({ slug: 'category', label: 'Category', plural: 'Categories' }),
    Object.freeze({ slug: 'post_tag', label: 'Tag', plural: 'Tags' }),
]);

// A slug lands verbatim in the block's `term` attribute and the variation
// name (`term-${slug}`), so constrain it to a safe identifier set —
// mirroring the PHP registry's guard.
const SAFE_SLUG_PATTERN = /^[a-z0-9_-]+$/;

let cached: ReadonlyArray<TaxonomyDescriptor> | null = null;

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

            result.push(Object.freeze({ slug, label, plural }));
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
    if (cached !== null) {
        return cached;
    }

    if (typeof document === 'undefined') {
        cached = FALLBACK_TAXONOMIES;
        return cached;
    }

    const parsed =
        parseFromElement(document.querySelector('[data-ap-visual-editor]')) ??
        parseFromElement(document.querySelector('[data-ap-site-editor]'));

    cached = parsed ?? FALLBACK_TAXONOMIES;
    return cached;
}

/**
 * Test-only: forget the cached snapshot so the next call re-reads the
 * DOM. Used by Vitest suites that swap the mount markup between cases.
 *
 * @internal
 */
export function refreshTaxonomies(): void {
    cached = null;
}
