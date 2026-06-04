/**
 * Query-family renderers (#521).
 *
 * Six dynamic blocks complete the artisanpack/query loop scaffolding:
 * query-no-results, query-pagination wrapper, the three pagination
 * leaves (previous / numbers / next), and query-title. Each leaf
 * reads its `_resolved*` attributes stamped by visual-editor's
 * QueryInliner before the tree reaches the renderer, so this file
 * stays pure — no data fetching, no DOM side effects, byte-shape
 * parity with the Blade and Vue counterparts.
 */

import type { JSX } from 'react';

import { attrInt, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

export function QueryNoResultsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-no-results', className]);

    return <div className={classes}>{children}</div>;
}

export function QueryPaginationBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-pagination', className]);

    return (
        <nav className={classes} aria-label="Pagination">
            {children}
        </nav>
    );
}

export function QueryPaginationNextBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = safeUrl(attrString(attributes._resolvedNextPageUrl));
    const label = attrString(attributes.label, 'Next Page');
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-pagination-next', className]);

    if (url === '') {
        return <span className={classes}>{label}</span>;
    }
    return (
        <a className={classes} href={url}>
            {label} &rarr;
        </a>
    );
}

export function QueryPaginationPreviousBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = safeUrl(attrString(attributes._resolvedPreviousPageUrl));
    const label = attrString(attributes.label, 'Previous Page');
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-pagination-previous', className]);

    if (url === '') {
        return <span className={classes}>{label}</span>;
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

export function QueryPaginationNumbersBlock({ attributes }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-query-pagination-numbers', className]);
    const current = attrInt(attributes._resolvedCurrentPage, 1);
    const rawPages = attributes._resolvedPageNumbers;
    const pages: ResolvedPage[] = Array.isArray(rawPages) ? (rawPages as ResolvedPage[]) : [];

    return (
        <div className={classes}>
            {pages.map((page, idx) => {
                const number =
                    typeof page.number === 'number' && Number.isFinite(page.number) ? page.number : 0;
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
    // Clamp level to the WP heading range (0 = paragraph, 1-6 = h1-h6).
    // Mirrors the Blade renderer's tag allow-list and the editor preview's
    // sanitizer so a malformed saved value never produces `<h7>`.
    const rawLevel = attrInt(attributes.level, 1);
    const level = rawLevel === 0 ? 0 : Math.min(6, Math.max(1, rawLevel));
    const Tag = (level === 0 ? 'p' : `h${level}`) as 'p' | 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return <Tag className={classes}>{title}</Tag>;
}
