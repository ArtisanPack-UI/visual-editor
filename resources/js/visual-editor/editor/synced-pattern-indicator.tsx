/**
 * Synced-pattern indicator filter.
 *
 * Wraps the block-list rendering of `core/block` (the synced-pattern
 * reference block) with a small overlay so authors instantly know
 * "this block is rendered as a synced pattern" — direct answer to D0's
 * "what am I editing" principle and per acceptance criteria for D5.
 *
 * The filter registers under `editor.BlockListBlock` (the standard
 * Gutenberg seam for adornments without forking the block type).
 * Idempotent across HMR reloads via a module-level guard so the
 * sandbox + post-editor entries can both call `registerSyncedPatternIndicator()`
 * without duplicate filters.
 */

import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { createElement, type ComponentType } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import './synced-pattern-indicator.css';

const FILTER_HOOK = 'editor.BlockListBlock';
const FILTER_NAMESPACE =
    'artisanpack-ui/visual-editor/synced-pattern-indicator';

interface BlockListBlockProps {
    name?: string;
    attributes?: { ref?: number | string };
    [key: string]: unknown;
}

// Page-global sentinel so the indicator is registered exactly once even
// when this module is loaded into multiple bundles (e.g. site-editor +
// post-editor entries on the same page, or HMR re-imports during
// development). A module-level `let` would only dedupe within a single
// module instance and miss the cross-bundle case.
const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.synced-pattern-indicator.registered'
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

function withSyncedPatternIndicator(
    BlockListBlock: ComponentType<BlockListBlockProps>
): ComponentType<BlockListBlockProps> {
    return function SyncedPatternIndicatorWrapper(
        props: BlockListBlockProps
    ): JSX.Element {
        const isSynced = props.name === 'core/block';

        if (!isSynced) {
            return createElement(BlockListBlock, props);
        }

        const ref =
            props.attributes !== undefined && props.attributes !== null
                ? props.attributes.ref
                : undefined;

        return createElement(
            'div',
            {
                className: 'ap-synced-pattern-indicator',
                'data-testid': 'ap-synced-pattern-indicator',
                'data-ref': ref ?? '',
            },
            createElement(
                'span',
                {
                    className: 'ap-synced-pattern-indicator__badge',
                    'aria-label': __('Synced pattern', TEXT_DOMAIN),
                    role: 'img',
                },
                __('Synced pattern', TEXT_DOMAIN)
            ),
            createElement(BlockListBlock, props)
        );
    };
}

export function registerSyncedPatternIndicator(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY] === true) {
        return;
    }

    addFilter(FILTER_HOOK, FILTER_NAMESPACE, withSyncedPatternIndicator);
    host[REGISTERED_KEY] = true;
}
