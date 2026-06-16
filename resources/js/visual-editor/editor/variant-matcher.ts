/**
 * Post-variant matcher engine (#591).
 *
 * Shared spec consumed by:
 *   - save-time compilation (writes a `position → variantId` map onto
 *     the parent `post-template`),
 *   - canvas preview (applies the matching variant to each preview
 *     post in the editor),
 *   - server renderer (`VariantResolver.php` mirrors this logic).
 *
 * Precedence (fixed):
 *   1. instance      (`position.kind === 'instance'`)
 *   2. position      (first, last, nth, range)
 *   3. pattern       (odd, even, every-nth)
 *   4. meta          (sticky, featured, taxonomy, author, has-featured-image)
 *   5. custom        (callback hooks — server-side only)
 *   6. base template (no variant matched)
 *
 * Ties inside a tier break on `priority` (lower wins), then document
 * order.
 */

export type MatcherKind = 'position' | 'pattern' | 'meta' | 'custom';

export interface PositionMatcher {
    readonly kind: 'position';
    /**
     * `first` | `last` | `nth:<n>` | `range:<from>-<to>` | `instance:<uuid>`.
     * Indices are 1-based to match author intuition ("first" === 1).
     */
    readonly value: string;
}

export interface PatternMatcher {
    readonly kind: 'pattern';
    /**
     * `odd` | `even` | `every-nth:<step>` | `every-nth:<step>:start:<offset>`.
     * Offset is 1-based (start of loop).
     */
    readonly value: string;
}

export interface MetaMatcher {
    readonly kind: 'meta';
    /**
     * `sticky` | `featured` | `has-featured-image` |
     * `taxonomy:<tax>:<slug>` | `author:<id>`.
     */
    readonly value: string;
}

export interface CustomMatcher {
    readonly kind: 'custom';
    /** `callback:<name>` — resolved by `apve_query_variant_match_<name>`. */
    readonly value: string;
}

export type Matcher = PositionMatcher | PatternMatcher | MetaMatcher | CustomMatcher;

export interface VariantDescriptor {
    /**
     * 0-based position of the variant within the parent post-template's
     * inner blocks. Doubles as a stable identifier — clientIds aren't
     * round-tripped through save/serialize, so the compiled map
     * references variants by document order.
     */
    readonly order: number;
    readonly matcher: Matcher;
    readonly priority: number;
    readonly label?: string;
}

const TIER_RANK: Record<MatcherKind, number> = {
    position: 2,
    pattern: 3,
    meta: 4,
    custom: 5,
};

const INSTANCE_TIER = 1;

function isInstance( matcher: Matcher ): boolean {
    return matcher.kind === 'position' && matcher.value.startsWith( 'instance:' );
}

function tierOf( matcher: Matcher ): number {
    return isInstance( matcher ) ? INSTANCE_TIER : TIER_RANK[ matcher.kind ];
}

/**
 * Sort variants by precedence tier → priority → document order.
 */
export function sortVariants( variants: ReadonlyArray<VariantDescriptor> ): VariantDescriptor[] {
    return [ ...variants ].sort( ( a, b ) => {
        const tierDiff = tierOf( a.matcher ) - tierOf( b.matcher );
        if ( tierDiff !== 0 ) {
            return tierDiff;
        }
        const priorityDiff = a.priority - b.priority;
        if ( priorityDiff !== 0 ) {
            return priorityDiff;
        }
        return a.order - b.order;
    } );
}

/**
 * Parse `every-nth:<step>` or `every-nth:<step>:start:<offset>`.
 */
function parseEveryNth( value: string ): { step: number; offset: number } | null {
    const parts = value.split( ':' );
    if ( parts[ 0 ] !== 'every-nth' ) {
        return null;
    }
    const step = Number.parseInt( parts[ 1 ] ?? '', 10 );
    if ( ! Number.isFinite( step ) || step < 1 ) {
        return null;
    }
    // Default offset = step so `every-nth:3` matches positions 3, 6, 9
    // (CSS `:nth-child(3n)` semantics). Explicit `:start:<n>` shifts to
    // <n>, <n+step>, <n+2*step>, …
    let offset = step;
    if ( parts[ 2 ] === 'start' ) {
        const parsed = Number.parseInt( parts[ 3 ] ?? '', 10 );
        if ( Number.isFinite( parsed ) && parsed >= 1 ) {
            offset = parsed;
        }
    }
    return { step, offset };
}

/**
 * Parse `nth:<n>` → 1-based position; `range:<from>-<to>` → inclusive
 * 1-based range; `first` / `last` → fixed positions (last needs the
 * total count, returned as `last`).
 */
function parsePosition( value: string ): { kind: 'first' | 'last' | 'nth' | 'range'; n?: number; from?: number; to?: number } | null {
    if ( 'first' === value ) {
        return { kind: 'first' };
    }
    if ( 'last' === value ) {
        return { kind: 'last' };
    }
    if ( value.startsWith( 'nth:' ) ) {
        const n = Number.parseInt( value.slice( 4 ), 10 );
        if ( Number.isFinite( n ) && n >= 1 ) {
            return { kind: 'nth', n };
        }
        return null;
    }
    if ( value.startsWith( 'range:' ) ) {
        const [ from, to ] = value.slice( 6 ).split( '-' ).map( ( raw ) => Number.parseInt( raw, 10 ) );
        if ( Number.isFinite( from ) && Number.isFinite( to ) && from >= 1 && to >= from ) {
            return { kind: 'range', from, to };
        }
        return null;
    }
    return null;
}

/**
 * Does a `position` matcher match the given 0-based loop index?
 * `total` is required for the `last` keyword.
 */
export function matchPosition( matcher: PositionMatcher, index: number, total: number ): boolean {
    if ( matcher.value.startsWith( 'instance:' ) ) {
        // `instance:` matchers are looked up by saved index map, not by
        // walking — see `compileStaticMap`.
        return false;
    }
    const parsed = parsePosition( matcher.value );
    if ( null === parsed ) {
        return false;
    }
    const position1 = index + 1;
    switch ( parsed.kind ) {
        case 'first':
            return 1 === position1;
        case 'last':
            return total >= 1 && position1 === total;
        case 'nth':
            return position1 === parsed.n;
        case 'range':
            return position1 >= ( parsed.from ?? 0 ) && position1 <= ( parsed.to ?? 0 );
        default:
            return false;
    }
}

/**
 * Does a `pattern` matcher match the given 0-based loop index?
 */
export function matchPattern( matcher: PatternMatcher, index: number ): boolean {
    const position1 = index + 1;
    if ( 'odd' === matcher.value ) {
        return 1 === position1 % 2;
    }
    if ( 'even' === matcher.value ) {
        return 0 === position1 % 2;
    }
    const everyNth = parseEveryNth( matcher.value );
    if ( null !== everyNth ) {
        return position1 >= everyNth.offset && 0 === ( position1 - everyNth.offset ) % everyNth.step;
    }
    return false;
}

/**
 * Loose structural metadata describing a preview post in the editor.
 * Mirrors what the canvas preview pipeline can derive from the
 * `useQueryPreview` payload. Server-side meta evaluation lives in
 * `VariantResolver.php` and reads the same conceptual fields off the
 * post model.
 */
export interface PreviewPostMeta {
    readonly sticky?: boolean;
    readonly featured?: boolean;
    readonly hasFeaturedImage?: boolean;
    readonly authorId?: number;
    readonly taxonomies?: Readonly<Record<string, ReadonlyArray<string>>>;
}

export function matchMeta( matcher: MetaMatcher, post: PreviewPostMeta ): boolean {
    if ( 'sticky' === matcher.value ) {
        return true === post.sticky;
    }
    if ( 'featured' === matcher.value ) {
        return true === post.featured;
    }
    if ( 'has-featured-image' === matcher.value ) {
        return true === post.hasFeaturedImage;
    }
    if ( matcher.value.startsWith( 'author:' ) ) {
        const id = Number.parseInt( matcher.value.slice( 7 ), 10 );
        return Number.isFinite( id ) && post.authorId === id;
    }
    if ( matcher.value.startsWith( 'taxonomy:' ) ) {
        const parts = matcher.value.split( ':' );
        const tax = parts[ 1 ];
        const slug = parts[ 2 ];
        if ( ! tax || ! slug || ! post.taxonomies ) {
            return false;
        }
        const terms = post.taxonomies[ tax ];
        return Array.isArray( terms ) && terms.includes( slug );
    }
    return false;
}

/**
 * Compile every `position` / `pattern` variant in `variants` into a
 * sparse `index → variantId` map for the given loop length. Static
 * rules cost nothing at render time once compiled. Returns indices
 * 0-based so the renderer can do a direct array lookup.
 *
 * Variants are walked in precedence order (`sortVariants`) and the
 * FIRST match for each index wins, mirroring the server-side cascade.
 *
 * `meta` and `custom` matchers are skipped here — they need post
 * context only available at render time.
 */
export function compileStaticMap(
    variants: ReadonlyArray<VariantDescriptor>,
    total: number
): Record<number, number> {
    const sorted = sortVariants( variants );
    const map: Record<number, number> = {};

    for ( const variant of sorted ) {
        if ( variant.matcher.kind !== 'position' && variant.matcher.kind !== 'pattern' ) {
            continue;
        }
        if ( isInstance( variant.matcher ) ) {
            // instance:<index1> form — the value is a 1-based loop
            // position. Treat it as a fixed-position match.
            const raw = variant.matcher.value.slice( 'instance:'.length );
            const idx1 = Number.parseInt( raw, 10 );
            if ( Number.isFinite( idx1 ) && idx1 >= 1 && idx1 <= total ) {
                const i0 = idx1 - 1;
                if ( map[ i0 ] === undefined ) {
                    map[ i0 ] = variant.order;
                }
            }
            continue;
        }

        for ( let i = 0; i < total; i++ ) {
            if ( map[ i ] !== undefined ) {
                continue;
            }
            const matches =
                variant.matcher.kind === 'position'
                    ? matchPosition( variant.matcher, i, total )
                    : matchPattern( variant.matcher, i );
            if ( matches ) {
                map[ i ] = variant.order;
            }
        }
    }

    return map;
}

/**
 * Editor-side runtime resolver: given the precompiled static map and
 * the full variant list, return the winning variant id (or `null` for
 * "use the base template") for a single loop iteration. Walks `meta`
 * matchers when the static map missed; `custom` matchers are
 * server-only and intentionally not evaluated here.
 */
export function resolveVariant(
    index: number,
    _total: number,
    post: PreviewPostMeta,
    variants: ReadonlyArray<VariantDescriptor>,
    staticMap: Record<number, number>
): number | null {
    const fromStatic = staticMap[ index ];
    if ( typeof fromStatic === 'number' ) {
        return fromStatic;
    }
    const sorted = sortVariants( variants );
    for ( const variant of sorted ) {
        if ( variant.matcher.kind === 'meta' && matchMeta( variant.matcher, post ) ) {
            return variant.order;
        }
    }
    return null;
}
