/**
 * Template fallback-chain helper.
 *
 * Mirrors the PHP `TemplateResolver::fallbackChain()` so the inspector's
 * Template tab can surface "Single post ▸ Single ▸ Index" (design brief
 * P7: template fallback chains are visible to users) without an extra
 * round-trip. The chain is a pure function of the slug, so duplicating it
 * client-side is safe — if the backend hierarchy ever changes, both
 * sides move together and the shared test coverage catches the drift.
 */

const GENERIC_FALLBACKS: readonly string[] = ['index'];
const SINGULAR_FALLBACKS: readonly string[] = ['singular', ...GENERIC_FALLBACKS];

/**
 * Returns the ordered fallback chain for the given template slug. The
 * first entry is the slug itself; the remainder is the list the backend
 * resolver walks if the primary template is missing. Entries are unique
 * and preserve order.
 */
export function fallbackChainForSlug(slug: string): readonly string[] {
    const primary = slug.trim();

    if (primary === '') {
        return GENERIC_FALLBACKS;
    }

    const chain: string[] = [primary];
    const append = (candidate: string): void => {
        if (candidate !== primary && !chain.includes(candidate)) {
            chain.push(candidate);
        }
    };

    // Specific-slug variants — e.g. `page-about` → `page` → `singular` → `index`.
    if (/^(page|single|singular)-[^-]+/.test(primary)) {
        const kind = primary.split('-', 1)[0];

        if (kind !== undefined) {
            append(kind);
        }

        SINGULAR_FALLBACKS.forEach(append);
    } else if (primary === 'page' || primary === 'single' || primary === 'singular') {
        SINGULAR_FALLBACKS.forEach(append);
    } else if (
        primary === 'front-page' ||
        primary === 'home' ||
        primary === '404' ||
        primary === 'search' ||
        primary === 'archive'
    ) {
        GENERIC_FALLBACKS.forEach(append);
    } else if (/^archive-[^-]+/.test(primary)) {
        append('archive');
        GENERIC_FALLBACKS.forEach(append);
    } else {
        // Custom templates fall back straight to `index` — themes can
        // override by shipping a more specific template.
        GENERIC_FALLBACKS.forEach(append);
    }

    return chain;
}

/**
 * Known template kinds the create-new picker surfaces. The design brief
 * (§3.4, §5.1) calls out "Front page, Home, Single post, Single page,
 * Archive, 404, Search, Custom" — kept in this order so the picker
 * reads left-to-right from most specific to most generic. The "Custom"
 * option is NOT in this list on purpose: the create dialog renders it
 * as a dedicated sentinel (`__custom__`) that swaps the fixed slug for
 * a free-text input, so mixing it into the kind-options array would
 * double up the affordance.
 */
export interface TemplateKindOption {
    slug: string;
    label: string;
    description: string;
}

export function getTemplateKindOptions(): readonly TemplateKindOption[] {
    return [
        {
            slug: 'front-page',
            label: 'Front page',
            description: 'The site homepage when a static front page is assigned.',
        },
        {
            slug: 'home',
            label: 'Blog home',
            description: 'The posts archive when not used as the front page.',
        },
        {
            slug: 'single',
            label: 'Single post',
            description: 'The default layout for individual posts.',
        },
        {
            slug: 'page',
            label: 'Single page',
            description: 'The default layout for individual pages.',
        },
        {
            slug: 'archive',
            label: 'Archive',
            description: 'Listings for categories, tags, and custom taxonomies.',
        },
        {
            slug: 'search',
            label: 'Search results',
            description: 'The search results page.',
        },
        {
            slug: '404',
            label: '404 (not found)',
            description: 'Shown when no other template matches the request.',
        },
        {
            slug: 'index',
            label: 'Index (fallback)',
            description: 'The last-resort fallback used when no other template matches.',
        },
    ];
}
