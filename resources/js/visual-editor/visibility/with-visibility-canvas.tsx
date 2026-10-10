/**
 * Editor-canvas preview of block visibility (#805).
 *
 * Two filters:
 *
 *  1. **`editor.BlockListBlock`** — screen-size rules hide the block
 *     in the canvas exactly where the front end would, using the front
 *     end's hidden width ranges (see `canvas-rules.ts`) scoped to the
 *     block's `clientId`. The range check runs against the canvas
 *     width the shell publishes: the previewed device width, or at
 *     `base` the site editor's measured canvas (it renders in-tree,
 *     where a `@media` query would test the browser window instead).
 *     When no width is published — the post editor at `base` — the
 *     ranges are emitted as `@media` rules, which inside its iframe
 *     canvas test the real canvas width. While the block (or anything inside it) is selected —
 *     e.g. picked from List View — it is revealed dimmed instead so
 *     its toolbar and inline editing keep working. Rules the canvas
 *     can't evaluate (master Hide, request / user / schedule rules)
 *     dim the block at every width.
 *
 *  2. **`blocks.registerBlockType`** — wraps the block's
 *     `__experimentalLabel` so List View rows carry a suffix such as
 *     "Button (Hidden at some screen sizes)". Registered after core's
 *     block-renaming filter so a custom block name keeps its suffix.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.13.0
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as blocksStore } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { select, useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { Fragment, useMemo, useSyncExternalStore } from 'react';
import type { ComponentType } from 'react';

import { getResponsiveRegistry } from '../responsive/registry';
import { getCanvasPreviewWidth, subscribeCanvasPreviewWidth } from '../responsive/use-canvas-preview-width';
import {
    hasServerEvaluatedRule,
    rangeMediaQuery,
    screenSizeHiddenRanges,
    visibilityListViewSuffix,
    type HiddenWidthRange,
    type MinWidthBreakpoint,
} from './canvas-rules';
import type { VisibilityAttribute } from './types';

const BLOCK_FILTER_HOOK = 'editor.BlockListBlock';
const LABEL_FILTER_HOOK = 'blocks.registerBlockType';

const BLOCK_FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/visibility-canvas';
const LABEL_FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/visibility-list-view-label';

/**
 * Runs after core's `core/metadata/addLabelCallback` (priority 10),
 * which only installs its callback when none exists yet.
 */
const LABEL_FILTER_PRIORITY = 20;

const REVEALED_CLASS = 'is-ap-vis-revealed';

/**
 * Opacity for dimmed blocks — readable, but clearly "not on the page
 * as-is".
 */
const DIMMED_OPACITY = 0.4;

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.visibility-canvas.registered',
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

interface BlockListBlockProps {
    name: string;
    clientId: string;
    attributes: Record<string, unknown> & {
        artisanpackVisibility?: VisibilityAttribute | null;
    };
    wrapperProps?: Record<string, unknown>;
    [key: string]: unknown;
}

type LabelCallback = (
    attributes: Record<string, unknown>,
    options: { context?: string },
) => unknown;

interface BlockSettingsLike {
    title?: string;
    attributes?: Record<string, unknown>;
    __experimentalLabel?: LabelCallback;
    [key: string]: unknown;
}

/**
 * The hydrated registry's legacy breakpoints — the set screen-size
 * visibility builds its ranges from, matching the server's
 * `BreakpointRegistry::all()`.
 */
export function visibilityBreakpoints(): MinWidthBreakpoint[] {
    const registry = getResponsiveRegistry();

    return registry.legacyPrefixes().flatMap((key) => {
        const minWidthPx = registry.get(key);

        return typeof minWidthPx === 'number' ? [{ key, minWidthPx }] : [];
    });
}

function readNoPreviewWidth(): null {
    return null;
}

function scopeClassFor(clientId: string): string {
    return `ap-vis-${clientId}`;
}

function rangeContains(range: HiddenWidthRange, widthPx: number): boolean {
    return widthPx >= range.minWidthPx && (range.maxWidthPx === null || widthPx <= range.maxWidthPx);
}

/**
 * Builds the block's scoped canvas CSS, or `''` when nothing applies.
 *
 * @param previewWidthPx The canvas width to evaluate against, or
 *                       `null` to defer to `@media` queries.
 */
export function buildVisibilityCanvasCss(
    clientId: string,
    value: VisibilityAttribute | null | undefined,
    breakpoints: readonly MinWidthBreakpoint[],
    previewWidthPx: number | null,
): string {
    const scope  = `.${scopeClassFor(clientId)}`;
    const hide   = `${scope}:not(.${REVEALED_CLASS}){display:none !important;}`
        + `${scope}.${REVEALED_CLASS}{opacity:${DIMMED_OPACITY};}`;
    const ranges = screenSizeHiddenRanges(value?.screenSize, breakpoints);
    let css      = '';

    if (hasServerEvaluatedRule(value)) {
        css += `${scope}{opacity:${DIMMED_OPACITY};}`;
    }

    if (previewWidthPx === null) {
        for (const range of ranges) {
            css += `@media ${rangeMediaQuery(range)}{${hide}}`;
        }
    } else if (ranges.some((range) => rangeContains(range, previewWidthPx))) {
        css += hide;
    }

    return css;
}

export const withVisibilityCanvas = createHigherOrderComponent(
    (BlockListBlock: ComponentType<BlockListBlockProps>) => {
        function VisibilityCanvasBlock(props: BlockListBlockProps): JSX.Element {
            const { clientId, attributes, wrapperProps } = props;
            const value = attributes.artisanpackVisibility ?? null;

            const gatesByWidth = useMemo(
                () => screenSizeHiddenRanges(value?.screenSize, visibilityBreakpoints()).length > 0,
                [value],
            );

            // Blocks without a screen-size rule read a constant
            // snapshot, so a viewport switch only re-renders the blocks
            // it can actually hide.
            const readPreviewWidth = gatesByWidth ? getCanvasPreviewWidth : readNoPreviewWidth;
            const previewWidthPx   = useSyncExternalStore(
                subscribeCanvasPreviewWidth,
                readPreviewWidth,
                readPreviewWidth,
            );

            const css = useMemo(
                () => buildVisibilityCanvasCss(clientId, value, visibilityBreakpoints(), previewWidthPx),
                [clientId, value, previewWidthPx],
            );

            // Only blocks the canvas can hide subscribe to selection.
            const isRevealed = useSelect(
                (selectStore) => {
                    if (!gatesByWidth) {
                        return false;
                    }

                    const store = selectStore(blockEditorStore) as {
                        isBlockSelected: (id: string) => boolean;
                        isBlockMultiSelected: (id: string) => boolean;
                        hasSelectedInnerBlock: (id: string, deep?: boolean) => boolean;
                    };

                    return store.isBlockSelected(clientId)
                        || store.isBlockMultiSelected(clientId)
                        || store.hasSelectedInnerBlock(clientId, true);
                },
                [clientId, gatesByWidth],
            );

            const nextWrapperProps = useMemo(() => {
                if (css === '') {
                    return wrapperProps;
                }

                const classes = [
                    (wrapperProps?.className as string | undefined) ?? '',
                    scopeClassFor(clientId),
                    isRevealed ? REVEALED_CLASS : '',
                ].filter((part) => part !== '');

                return { ...(wrapperProps ?? {}), className: classes.join(' ') };
            }, [css, wrapperProps, clientId, isRevealed]);

            if (css === '') {
                return <BlockListBlock {...props} />;
            }

            return (
                <Fragment>
                    <style data-ap-vis-scope={clientId}>{css}</style>
                    <BlockListBlock {...props} wrapperProps={nextWrapperProps} />
                </Fragment>
            );
        }

        VisibilityCanvasBlock.displayName = 'VisibilityCanvasBlock';

        return VisibilityCanvasBlock;
    },
    'withVisibilityCanvas',
);

function labelToText(label: unknown): string | null {
    if (typeof label === 'string') {
        return label === '' ? null : label;
    }

    if (label && typeof (label as { toPlainText?: unknown }).toPlainText === 'function') {
        return (label as { toPlainText: () => string }).toPlainText();
    }

    return null;
}

/**
 * Wraps `__experimentalLabel` so List View rows describe the block's
 * visibility. Blocks without the visibility attribute are untouched.
 */
export function addVisibilityListViewLabel(settings: BlockSettingsLike, name: string): BlockSettingsLike {
    if (!settings.attributes || !('artisanpackVisibility' in settings.attributes)) {
        return settings;
    }

    const original = settings.__experimentalLabel;

    const label: LabelCallback = (attributes, options) => {
        const base = original?.(attributes, options);

        if (options?.context !== 'list-view') {
            return base;
        }

        const suffix = visibilityListViewSuffix(
            attributes.artisanpackVisibility as VisibilityAttribute | null | undefined,
            visibilityBreakpoints(),
        );

        if (suffix === null) {
            return base;
        }

        // List View falls back to the active variation's title (e.g.
        // "Row" for a Group) only when the label equals the block
        // title, so resolve that fallback here before suffixing.
        const variation = (select(blocksStore) as {
            getActiveBlockVariation?: (blockName: string, attrs: Record<string, unknown>) => { title?: string } | undefined;
        }).getActiveBlockVariation?.(name, attributes);

        const text = labelToText(base) ?? variation?.title ?? settings.title ?? name;

        /* translators: 1: block name shown in List View, 2: visibility state such as "Hidden". */
        return sprintf(__('%1$s (%2$s)', 'artisanpack-visual-editor'), text, suffix);
    };

    return { ...settings, __experimentalLabel: label };
}

export function registerVisibilityCanvas(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY]) {
        return;
    }

    addFilter(BLOCK_FILTER_HOOK, BLOCK_FILTER_NAMESPACE, withVisibilityCanvas);
    addFilter(LABEL_FILTER_HOOK, LABEL_FILTER_NAMESPACE, addVisibilityListViewLabel, LABEL_FILTER_PRIORITY);
    host[REGISTERED_KEY] = true;
}
