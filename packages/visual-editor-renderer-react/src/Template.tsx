/**
 * Public React component for rendering a template by slug.
 *
 * Walks the WordPress-style fallback chain (`single-{slug}` → `single`
 * → `index`, `page-{slug}` → `page` → `index`, etc.) over the supplied
 * `templates` list, and renders the first match's block tree via
 * {@link BlockTree}. Template-part references inside the matched
 * template are inlined via the supplied `templateParts` collection.
 *
 * Resolution failures render an empty wrapper in production; in dev the
 * wrapper carries a `data-ve-resolution-error` attribute so the
 * developer can spot the misconfiguration in the inspector.
 */

import { useMemo } from 'react';
import type { ReactElement } from 'react';
import { BlockTree } from './BlockTree';
import {
    DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    resolveTemplate,
    templateFallbackChain,
} from './templateParts';
import type { TemplatePartRecord, TemplateRecord } from './templateParts';

const SLUG_CLASS_PATTERN = /[^a-zA-Z0-9_-]/g;

function isDevelopment(): boolean {
    if (typeof process === 'undefined') {
        return false;
    }

    const env = process.env?.NODE_ENV;

    return env !== 'production';
}

export interface TemplateProps {
    slug: string;
    theme?: string;
    templates: TemplateRecord[];
    templateParts?: TemplatePartRecord[];
    dynamicBlockEndpoint?: string;
    fetchOptions?: RequestInit;
    maxTemplatePartDepth?: number;
}

export function Template({
    slug,
    theme,
    templates,
    templateParts = [],
    dynamicBlockEndpoint,
    fetchOptions,
    maxTemplatePartDepth = DEFAULT_MAX_TEMPLATE_PART_DEPTH,
}: TemplateProps): ReactElement {
    const matched = useMemo(() => resolveTemplate(templates, slug, theme), [templates, slug, theme]);
    const fallbackChain = useMemo(() => templateFallbackChain(slug), [slug]);

    const wrapperClasses = ['wp-site-blocks'];

    if (matched !== undefined) {
        wrapperClasses.push(`wp-site-blocks--${matched.slug.replace(SLUG_CLASS_PATTERN, '-')}`);
    }

    const dataAttributes: Record<string, string> = {
        'data-ve-template': slug,
    };

    if (matched !== undefined && matched.slug !== slug) {
        dataAttributes['data-ve-matched-template'] = matched.slug;
    }

    if (matched === undefined && isDevelopment()) {
        dataAttributes['data-ve-resolution-error'] = 'no-matching-template';
        dataAttributes['data-ve-fallback-chain'] = fallbackChain.join(',');
    }

    if (matched === undefined) {
        return <div className={wrapperClasses.join(' ')} {...dataAttributes} />;
    }

    return (
        <div className={wrapperClasses.join(' ')} {...dataAttributes}>
            <BlockTree
                tree={matched.blocks}
                templateParts={templateParts}
                defaultTheme={theme ?? matched.theme}
                dynamicBlockEndpoint={dynamicBlockEndpoint}
                fetchOptions={fetchOptions}
                maxTemplatePartDepth={maxTemplatePartDepth}
            />
        </div>
    );
}
