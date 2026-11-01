/**
 * Sources the Visibility panel's Content subsection offers (#1030).
 *
 * The server publishes them on the editor mount element as
 * `data-visibility-content-sources`, which is how a source registered
 * through `ap.visualEditor.visibility.registerContentSources` reaches
 * the panel. The built-in list is the fallback for a mount that doesn't
 * carry the attribute, and mirrors `Visibility/ContentSources/*.php`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import { __ } from '@wordpress/i18n';

import type { VisibilityContentSourceOption } from './types';

let cachedServerSources: VisibilityContentSourceOption[] | null | undefined;

function builtInSources(): VisibilityContentSourceOption[] {
    return [
        {
            key: 'query',
            label: __('Query Loop', 'artisanpack-visual-editor'),
            blocks: ['artisanpack/query', 'core/query'],
            conditions: [{ value: 'hasResults', label: __('Has results', 'artisanpack-visual-editor') }],
        },
        {
            key: 'relatedPosts',
            label: __('Related Posts', 'artisanpack-visual-editor'),
            blocks: ['artisanpack/related-posts'],
            conditions: [{ value: 'hasResults', label: __('Has results', 'artisanpack-visual-editor') }],
        },
        {
            key: 'postNavigation',
            label: __('Post Navigation', 'artisanpack-visual-editor'),
            blocks: ['artisanpack/post-navigation-link', 'core/post-navigation-link'],
            conditions: [
                { value: 'hasEither', label: __('Has a previous or next post', 'artisanpack-visual-editor') },
                { value: 'hasPrevious', label: __('Has a previous post', 'artisanpack-visual-editor') },
                { value: 'hasNext', label: __('Has a next post', 'artisanpack-visual-editor') },
            ],
        },
        {
            key: 'pagination',
            label: __('Query Pagination', 'artisanpack-visual-editor'),
            blocks: ['artisanpack/query-pagination', 'core/query-pagination', 'artisanpack/query', 'core/query'],
            conditions: [{ value: 'hasMultiplePages', label: __('Has more than one page', 'artisanpack-visual-editor') }],
        },
    ];
}

function isSource(entry: unknown): entry is VisibilityContentSourceOption {
    const source = entry as Partial<VisibilityContentSourceOption> | null;

    return typeof source === 'object'
        && source !== null
        && typeof source.key === 'string'
        && source.key !== ''
        && typeof source.label === 'string'
        && Array.isArray(source.blocks)
        && Array.isArray(source.conditions)
        && source.conditions.length > 0;
}

function parseFromElement(element: Element | null): VisibilityContentSourceOption[] | null {
    if (!(element instanceof HTMLElement)) {
        return null;
    }

    const raw = element.dataset.visibilityContentSources?.trim();

    if (!raw) {
        return null;
    }

    try {
        const parsed: unknown = JSON.parse(raw);
        const sources = Array.isArray(parsed) ? parsed.filter(isSource) : [];

        return sources.length > 0 ? sources : null;
    } catch {
        return null;
    }
}

/**
 * The sources to offer: the server's list, or the built-ins.
 */
export function getVisibilityContentSources(): VisibilityContentSourceOption[] {
    if (cachedServerSources === undefined) {
        cachedServerSources = typeof document === 'undefined'
            ? null
            : parseFromElement(document.querySelector('[data-ap-visual-editor]'))
                ?? parseFromElement(document.querySelector('[data-ap-site-editor]'));
    }

    return cachedServerSources ?? builtInSources();
}

/**
 * Forgets the parsed server list. Used by tests that swap the mount
 * element.
 */
export function refreshVisibilityContentSources(): void {
    cachedServerSources = undefined;
}

interface BlockTreeReader {
    getBlockName: (clientId: string) => string | null;
    getBlockOrder: (clientId: string) => string[];
}

/**
 * The names of a block and its descendants, in document order. Reads
 * the order per block because a template part or synced pattern keeps
 * its inner blocks out of `getBlock()`'s tree.
 */
export function collectSubtreeBlockNames(store: BlockTreeReader, clientId: string): string[] {
    const name  = store.getBlockName(clientId);
    const names = name === null ? [] : [name];

    for (const childId of store.getBlockOrder(clientId)) {
        names.push(...collectSubtreeBlockNames(store, childId));
    }

    return names;
}
