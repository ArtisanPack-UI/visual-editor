/**
 * Related Posts — grid layout helpers (#964).
 *
 * When the related items sit directly under the block (no nested Post
 * Template), the block's own `displayLayout` lays them out: `list`, or
 * `grid` with `columns`. The renderers read `displayLayout.columns`,
 * else the legacy `numColumns`, else 3, so the editor control reads the
 * same fallbacks and writes both attributes back in sync.
 */

export type RelatedPostsLayoutType = 'list' | 'grid';

export interface RelatedPostsDisplayLayout {
    readonly type?: string;
    readonly columns?: number | string;
}

export interface RelatedPostsLayoutAttributes {
    readonly numColumns?: number;
    readonly displayLayout?: RelatedPostsDisplayLayout;
}

export interface RelatedPostsLayout {
    readonly type: RelatedPostsLayoutType;
    readonly columns: number;
}

export const RELATED_POSTS_MIN_COLUMNS = 2;
export const RELATED_POSTS_MAX_COLUMNS = 6;
const DEFAULT_COLUMNS = 3;

const POST_TEMPLATE_BLOCKS: ReadonlyArray<string> = [
    'artisanpack/post-template',
    'core/post-template',
];

function parseColumns(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.trunc(value);
    }
    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : null;
    }
    return null;
}

/**
 * The block's effective layout, with the column count clamped to the
 * range the Columns control offers.
 */
export function readRelatedPostsLayout(
    attributes: RelatedPostsLayoutAttributes
): RelatedPostsLayout {
    const displayLayout = attributes.displayLayout ?? {};
    const type: RelatedPostsLayoutType =
        displayLayout.type === 'grid' ? 'grid' : 'list';
    const columns =
        parseColumns(displayLayout.columns) ??
        parseColumns(attributes.numColumns) ??
        DEFAULT_COLUMNS;

    return {
        type,
        columns: Math.max(
            RELATED_POSTS_MIN_COLUMNS,
            Math.min(RELATED_POSTS_MAX_COLUMNS, columns)
        ),
    };
}

/**
 * Attribute changes for a new layout: `displayLayout` plus the legacy
 * `numColumns` mirror, so content read by either key agrees.
 */
export function relatedPostsLayoutChanges(
    next: RelatedPostsLayout
): { displayLayout: { type: RelatedPostsLayoutType; columns: number }; numColumns: number } {
    return {
        displayLayout: { type: next.type, columns: next.columns },
        numColumns: next.columns,
    };
}

/**
 * Whether a nested Post Template owns the layout, in which case the
 * Related Posts wrapper ignores its own `displayLayout`.
 */
export function hasNestedPostTemplate(
    innerBlocks: ReadonlyArray<{ readonly name?: string }>
): boolean {
    return innerBlocks.some(
        (block) =>
            typeof block.name === 'string' &&
            POST_TEMPLATE_BLOCKS.includes(block.name)
    );
}
