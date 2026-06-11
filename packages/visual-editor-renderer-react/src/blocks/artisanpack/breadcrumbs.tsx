/**
 * React renderer for the `artisanpack/breadcrumbs` block (CW0 pilot — #496).
 *
 * Mirrors the Blade partial at
 * `packages/visual-editor-renderer-blade/resources/views/blocks/artisanpack/breadcrumbs.blade.php`
 * and the Vue renderer so every environment emits identical markup. The
 * trail itself is server-resolved and arrives on the `_resolvedTrail`
 * attribute as an array of `{ label, url?, current? }` entries; this
 * renderer is responsible for the wrapper, separator chevron, and
 * (optionally) the schema.org BreadcrumbList microdata.
 */

import type { ReactElement } from 'react';

import { attrArray, attrBoolean, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

type SeparatorIconName =
    | 'arrow-right'
    | 'chevron-right'
    | 'chevron-double-right'
    | 'long-arrow-right';

const VALID_SEPARATORS: ReadonlyArray<SeparatorIconName> = [
    'arrow-right',
    'chevron-right',
    'chevron-double-right',
    'long-arrow-right',
];

const SEPARATOR_PATHS: Readonly<Record<SeparatorIconName, string>> = {
    'arrow-right': 'M5 12h14m-6-6 6 6-6 6',
    'chevron-right': 'm9 6 6 6-6 6',
    'chevron-double-right': 'm6 6 6 6-6 6m6-12 6 6-6 6',
    'long-arrow-right': 'M3 12h17m-5-5 5 5-5 5',
};

interface TrailItem {
    readonly label: string;
    readonly url: string | null;
    readonly current: boolean;
}

function normalizeSeparator(value: unknown): SeparatorIconName {
    const raw = attrString(value, 'chevron-right');
    return (VALID_SEPARATORS as ReadonlyArray<string>).includes(raw)
        ? (raw as SeparatorIconName)
        : 'chevron-right';
}

function normalizeTrail(value: unknown): ReadonlyArray<TrailItem> {
    const items: TrailItem[] = [];

    for (const entry of attrArray(value)) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const record = entry as Record<string, unknown>;
        const label = attrString(record.label);

        if (label === '') {
            continue;
        }

        const sanitizedUrl = safeUrl(record.url);

        items.push({
            label,
            url: sanitizedUrl === '' ? null : sanitizedUrl,
            current: attrBoolean(record.current, false),
        });
    }

    return items;
}

export function BreadcrumbsBlock({ attributes }: BlockRendererProps): ReactElement {
    const separatorIcon = normalizeSeparator(attributes.separatorIcon);
    const breadcrumbsSchema = attrBoolean(attributes.breadcrumbsSchema, true);
    const trail = normalizeTrail(attributes._resolvedTrail);
    const ariaLabel = attrString(attributes.ariaLabel, 'Breadcrumb');
    const className = attrString(attributes.className);

    const wrapperClasses = classList(['ap-breadcrumbs', className]);
    const separatorPath = SEPARATOR_PATHS[separatorIcon];

    const listProps = breadcrumbsSchema
        ? {
              className: 'ap-breadcrumbs__list',
              itemScope: true,
              itemType: 'https://schema.org/BreadcrumbList',
          }
        : { className: 'ap-breadcrumbs__list' };

    return (
        <nav className={wrapperClasses} aria-label={ariaLabel}>
            <ol {...listProps}>
                {trail.map((item, index) => {
                    const isLast = index === trail.length - 1;
                    const position = index + 1;

                    const itemProps = breadcrumbsSchema
                        ? {
                              className: 'ap-breadcrumbs__item',
                              itemProp: 'itemListElement',
                              itemScope: true,
                              itemType: 'https://schema.org/ListItem',
                          }
                        : { className: 'ap-breadcrumbs__item' };

                    return (
                        <li key={`${position}-${item.label}`} {...itemProps}>
                            {item.url !== null && !item.current ? (
                                <a
                                    className="ap-breadcrumbs__link"
                                    href={item.url}
                                    {...(breadcrumbsSchema ? { itemProp: 'item' } : {})}
                                >
                                    <span
                                        {...(breadcrumbsSchema ? { itemProp: 'name' } : {})}
                                    >
                                        {item.label}
                                    </span>
                                </a>
                            ) : (
                                <span
                                    className="ap-breadcrumbs__current"
                                    {...(item.current ? { 'aria-current': 'page' as const } : {})}
                                    {...(breadcrumbsSchema ? { itemProp: 'name' } : {})}
                                >
                                    {item.label}
                                </span>
                            )}
                            {breadcrumbsSchema && (
                                <meta itemProp="position" content={String(position)} />
                            )}
                            {!isLast && (
                                <span
                                    className="ap-breadcrumbs__separator"
                                    aria-hidden="true"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        width="1em"
                                        height="1em"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        aria-hidden="true"
                                        focusable="false"
                                    >
                                        <path d={separatorPath} />
                                    </svg>
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
