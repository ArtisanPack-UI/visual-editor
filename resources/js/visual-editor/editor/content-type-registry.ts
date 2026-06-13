/**
 * Content-type registry — reads the host-registered cms-framework
 * content types stamped onto the editor mount element as the
 * `data-content-types` JSON attribute (set by
 * {@see ArtisanPackUI\VisualEditor\View\Components\VisualEditorComponent}).
 *
 * Consumers (notably `artisanpack/single-content`) use this list to
 * register one block variation per content type so authors can pick a
 * pre-configured Single Content block straight from the inserter.
 *
 * The list is resolved at module-import time. Subsequent calls return
 * the cached snapshot — if the editor mounts after this module has
 * been imported (e.g. tests), {@see refreshContentTypes} can be called
 * to re-read the DOM.
 *
 * @since 1.0.0
 */

export interface ContentTypeDescriptor {
    readonly slug: string;
    readonly plural: string;
    readonly label: string;
}

const FALLBACK_TYPES: ReadonlyArray<ContentTypeDescriptor> = Object.freeze([
    Object.freeze({ slug: 'post', plural: 'posts', label: 'Post' }),
    Object.freeze({ slug: 'page', plural: 'pages', label: 'Page' }),
]);

// Plural is later interpolated into the REST collection URL by
// `single-content/edit.tsx` (`${API_BASE}/${plural}`). Mirror the
// `SAFE_SLUG_PATTERN` over there so a malformed registry entry can't
// produce a broken URL or — worst case — pin a path-traversal segment
// into the fetch.
const SAFE_PLURAL_PATTERN = /^[a-z0-9_-]+$/;

let cached: ReadonlyArray<ContentTypeDescriptor> | null = null;

function parseFromElement(
    element: Element | null
): ReadonlyArray<ContentTypeDescriptor> | null {
    if (element === null || !(element instanceof HTMLElement)) {
        return null;
    }

    const raw = element.dataset.contentTypes?.trim();
    if (!raw) {
        return null;
    }

    try {
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return null;
        }

        const result: ContentTypeDescriptor[] = [];
        for (const entry of parsed) {
            if (
                entry === null ||
                typeof entry !== 'object' ||
                typeof (entry as { slug?: unknown }).slug !== 'string' ||
                typeof (entry as { label?: unknown }).label !== 'string'
            ) {
                continue;
            }

            const slug = ((entry as { slug: string }).slug).trim().toLowerCase();
            const label = ((entry as { label: string }).label).trim();
            const rawPlural = (entry as { plural?: unknown }).plural;

            // Skip entries whose slug doesn't match the safe URL-path
            // character set — `${slug}s` would otherwise leak through
            // as a malformed REST collection segment when the plural
            // fallback below kicks in.
            if (!SAFE_PLURAL_PATTERN.test(slug)) {
                continue;
            }

            // Constrain plural to the same character set as a safe URL
            // path segment so it can be interpolated into the REST
            // collection URL (`${API_BASE}/${plural}`) without escaping.
            // Anything else falls back to the slug-based pluralization,
            // which is now safe because the slug was validated above.
            const normalizedPlural =
                typeof rawPlural === 'string'
                    ? rawPlural.trim().toLowerCase()
                    : '';
            const plural = SAFE_PLURAL_PATTERN.test(normalizedPlural)
                ? normalizedPlural
                : `${slug}s`;

            if (label !== '') {
                result.push(Object.freeze({ slug, plural, label }));
            }
        }

        return result.length > 0 ? Object.freeze(result) : null;
    } catch {
        return null;
    }
}

/**
 * Returns the cms-framework content types stamped onto the mount
 * element. Falls back to `[post, page]` when the attribute is missing
 * (the conventional cms-framework defaults), so the inserter always
 * surfaces at least one variation per common content type even in dev
 * environments where the attribute hasn't been wired up yet.
 *
 * @since 1.0.0
 */
export function getContentTypes(): ReadonlyArray<ContentTypeDescriptor> {
    if (cached !== null) {
        return cached;
    }

    if (typeof document === 'undefined') {
        cached = FALLBACK_TYPES;
        return cached;
    }

    const parsed = parseFromElement(
        document.querySelector('[data-ap-visual-editor]')
    );

    cached = parsed ?? FALLBACK_TYPES;
    return cached;
}

/**
 * Test-only: forget the cached snapshot so the next call re-reads the
 * DOM. Used by Vitest suites that swap the mount markup between cases.
 *
 * @internal
 */
export function refreshContentTypes(): void {
    cached = null;
}
