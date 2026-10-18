/**
 * Query-family renderers (#521).
 *
 * Six dynamic blocks complete the artisanpack/query loop scaffolding:
 * query-no-results, query-pagination wrapper, the three pagination
 * leaves (previous / numbers / next), and query-title. Each leaf
 * reads its `_resolved*` attributes stamped by visual-editor's
 * QueryInliner before the tree reaches the renderer, so this file
 * stays pure — no data fetching, no DOM side effects, byte-shape
 * parity with the Blade and Vue counterparts. Query Total (#962) joins
 * them as a seventh leaf.
 */

import type { JSX } from 'react';

import { attrInt, attrString, classList } from '../../support/attributes';
import { applyBlockGap } from '../../support/blockGap';
import { layoutWrapperClasses } from '../../support/layoutContainer';
import { presetClasses } from '../../support/presetClasses';
import { safeUrl } from '../../support/urlSanitizer';
import type { Block, BlockRendererProps } from '../../types';

export function QueryNoResultsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-no-results', className]);

    return <div className={classes}>{children}</div>;
}

const PAGINATION_LEAF = /^(core|artisanpack)\/query-pagination-(previous|numbers|next)$/;

/**
 * Whether a pagination wrapper renders: not on one page when it holds only
 * the previous / numbers / next leaves, which render nothing there, as
 * WordPress skips an empty wrapper (#955). Any other inner block keeps it,
 * and an unstamped wrapper (no query around it) renders.
 */
export function queryPaginationRenders(attributes: Record<string, unknown>, innerBlocks: Block[]): boolean {
    return (
        attrInt(attributes._resolvedTotalPages, 2) > 1 ||
        !innerBlocks.every((child) => PAGINATION_LEAF.test(child.name))
    );
}

export function QueryPaginationBlock({ attributes, innerBlocks, children }: BlockRendererProps): JSX.Element | null {
    if (!queryPaginationRenders(attributes, innerBlocks ?? [])) {
        return null;
    }

    const className = attrString(attributes.className);
    // Always a flex layout, as in `query-pagination.blade.php` (#956).
    const classes = classList([
        'wp-block-query-pagination',
        ...layoutWrapperClasses(attributes, 'query-pagination', 'is-layout-flex'),
        className,
    ]);
    const gapStyle = applyBlockGap(attributes);

    return (
        <nav
            className={classes}
            aria-label="Pagination navigation"
            style={Object.keys(gapStyle).length > 0 ? (gapStyle as React.CSSProperties) : undefined}
        >
            {children}
        </nav>
    );
}

// Inactive states render nothing on the front end (issue #599) — the
// editor still shows a muted placeholder so authors can edit the
// block; the public renderer stays quiet so themes don't have to
// style an always-present-but-sometimes-inactive affordance.
export function QueryPaginationNextBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const url = safeUrl(attrString(attributes._resolvedNextPageUrl));
    const label = attrString(attributes.label, 'Next Page');
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-pagination-next', className]);

    if (url === '') {
        return null;
    }
    return (
        <a className={classes} href={url}>
            {label} &rarr;
        </a>
    );
}

export function QueryPaginationPreviousBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const url = safeUrl(attrString(attributes._resolvedPreviousPageUrl));
    const label = attrString(attributes.label, 'Previous Page');
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-pagination-previous', className]);

    if (url === '') {
        return null;
    }
    return (
        <a className={classes} href={url}>
            &larr; {label}
        </a>
    );
}

interface ResolvedPage {
    readonly number?: unknown;
    readonly url?: unknown;
}

export function QueryPaginationNumbersBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-pagination-numbers', className]);
    const current = attrInt(attributes._resolvedCurrentPage, 1);
    const rawPages = attributes._resolvedPageNumbers;
    const pages: ResolvedPage[] = Array.isArray(rawPages) ? (rawPages as ResolvedPage[]) : [];

    // Fewer than two pages renders nothing, as `paginate_links()` does (#955).
    if (pages.length < 2) {
        return null;
    }

    return (
        <div className={classes}>
            {pages.map((page, idx) => {
                // Accept either a number or a numeric string for `page.number` so the React
                // / Vue renderers stay in parity with the Blade partial, whose `is_numeric()`
                // check passes for both `2` and `"2"`. Hosts that pass JSON-decoded payloads
                // through PHP's `json_encode` typically get strings here.
                const rawNumber = page.number;
                const parsed =
                    typeof rawNumber === 'number'
                        ? rawNumber
                        : typeof rawNumber === 'string' && rawNumber.trim() !== ''
                          ? Number(rawNumber)
                          : Number.NaN;
                const number = Number.isFinite(parsed) ? parsed : 0;
                if (number === 0) {
                    return null;
                }
                const href = safeUrl(typeof page.url === 'string' ? page.url : '');
                const isCurrent = number === current;

                // Only the actual current page gets aria-current="page".
                // A page that's missing its URL still renders as a
                // non-interactive span (no link target) but should not
                // claim to be the current page. Mirrors the Blade +
                // Vue parity (and the comments-pagination-numbers
                // fork's contract).
                if (isCurrent) {
                    return (
                        <span
                            key={`pg-${idx}-${number}`}
                            className="page-numbers current"
                            aria-current="page"
                        >
                            {number}
                        </span>
                    );
                }
                if (href === '') {
                    return (
                        <span key={`pg-${idx}-${number}`} className="page-numbers">
                            {number}
                        </span>
                    );
                }
                return (
                    <a key={`pg-${idx}-${number}`} className="page-numbers" href={href}>
                        {number}
                    </a>
                );
            })}
        </div>
    );
}

export function QueryTitleBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const title = attrString(attributes._resolvedQueryTitle);

    if (title === '') {
        return null;
    }

    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-title', className]);
    // Validate `level` against the WP heading range (0 = paragraph, 1-6 = h1-h6)
    // and fall back to h1 for out-of-range values — mirrors the Blade partial's
    // tag allow-list so the React / Vue / Blade renderers stay byte-equivalent.
    const rawLevel = attrInt(attributes.level, 1);
    const Tag = (
        rawLevel === 0
            ? 'p'
            : rawLevel >= 1 && rawLevel <= 6
              ? (`h${rawLevel}` as const)
              : 'h1'
    ) as 'p' | 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return <Tag className={classes}>{title}</Tag>;
}

function numericAttr(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.trunc(value);
    }
    if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) {
        return Math.trunc(Number(value));
    }
    return null;
}

/**
 * The text parts of a query-total line (#962): the noun for the total
 * and, for `range-display` with results, the "first–last" range of the
 * current page. Mirrored by the Vue renderer; both match
 * `query-total.blade.php`. Returns null outside a resolved query.
 */
export function queryTotalParts(
    attributes: Record<string, unknown>,
): { total: number; range: string | null; noun: string; prefix: string; suffix: string } | null {
    const rawTotal = numericAttr(attributes._resolvedTotal);
    if (rawTotal === null) {
        return null;
    }
    const total = Math.max(0, rawTotal);
    const text = (key: string, fallback: string): string =>
        typeof attributes[key] === 'string' ? (attributes[key] as string) : fallback;

    const noun = total === 1 ? text('singular', 'result') : text('plural', 'results');
    let range: string | null = null;

    if ((attributes.displayType ?? 'total-results') === 'range-display' && total > 0) {
        const current = Math.max(1, numericAttr(attributes._resolvedCurrentPage) ?? 1);
        const perPage = Math.max(0, numericAttr(attributes._resolvedPerPage) ?? 0);
        const rawItems = numericAttr(attributes._resolvedItems);
        const items = rawItems === null ? null : Math.max(0, rawItems);

        const first = perPage === 0 ? 1 : Math.min(total, (current - 1) * perPage + 1);
        let last = perPage === 0 ? total : Math.min(total, current * perPage);
        if (items !== null && items > 0) {
            last = Math.min(total, first + items - 1);
        }
        range = first === last ? String(first) : `${first}–${last}`;
    }

    return { total, range, noun, prefix: text('prefix', ''), suffix: text('suffix', '') };
}

export function QueryTotalBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const parts = queryTotalParts(attributes);

    if (parts === null) {
        return null;
    }

    const className = attrString(attributes.className);
    // Preset colour / font-size classes match the Blade partial's
    // `BlockSupports::wrapperAttrs()` output (#888).
    const classes = classList(['wp-block-query-total', ...presetClasses(attributes), className]);

    return (
        <div className={classes}>
            {parts.prefix}
            {parts.range !== null ? (
                <>
                    <strong>{parts.range}</strong> of <strong>{parts.total}</strong>
                </>
            ) : (
                <strong>{parts.total}</strong>
            )}
            {`${parts.noun !== '' ? ` ${parts.noun}` : ''}${parts.suffix}`}
        </div>
    );
}
