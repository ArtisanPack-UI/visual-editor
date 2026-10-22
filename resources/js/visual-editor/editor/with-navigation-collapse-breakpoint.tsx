/**
 * Navigation collapse breakpoint — inspector control + canvas preview (#977).
 *
 * Two filters on upstream `core/navigation`:
 *
 * - `editor.BlockEdit` adds a "Collapse below" select (Default 600px /
 *   Mobile / Tablet, widths read from the breakpoint registry) to the
 *   inspector while the overlay is `mobile`. Upstream's own Display panel
 *   only renders when the menu has submenus and takes no extra controls,
 *   so the select sits in its own panel right after upstream's Overlay
 *   panel.
 * - `editor.BlockListBlock` stamps `collapses-at-{key}` on the canvas
 *   wrapper and publishes the matching rules into the canvas document
 *   (post-editor iframe or site-editor in-tree canvas), so the preview
 *   collapses at the same width the front end does — the canvas loads
 *   the same block-library 600px rules the renderers override.
 *
 * Both are idempotent across the post-editor and site-editor bootstrap
 * paths via a page-global sentinel. The attribute itself is registered
 * by `navigation-collapse-breakpoint.ts`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.2
 */

import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, type ComponentType } from 'react';

import { getResponsiveRegistry } from '../responsive/registry';
import { useCanvasScopedStyle } from '../support/canvas-scoped-styles';
import { TEXT_DOMAIN } from '../vendor/i18n';
import {
    NAVIGATION_COLLAPSE_CLASS_PREFIX,
    navigationCollapseCss,
    navigationCollapseKey,
} from './navigation-collapse-breakpoint';

const PANEL_NAMESPACE = 'artisanpack-ui/visual-editor/navigation-collapse-breakpoint-control';
const CANVAS_NAMESPACE = 'artisanpack-ui/visual-editor/navigation-collapse-breakpoint-canvas';

/** Canvas style channel for the collapse rules. */
export const NAVIGATION_COLLAPSE_STYLE_CHANNEL = 'navigation-collapse';

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.navigation-collapse-breakpoint.registered'
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

interface BlockEditProps {
    name: string;
    attributes: Record<string, unknown>;
    setAttributes: (updates: Record<string, unknown>) => void;
    [key: string]: unknown;
}

interface BlockListBlockProps {
    name?: string;
    clientId?: string;
    attributes?: Record<string, unknown>;
    wrapperProps?: Record<string, unknown>;
    [key: string]: unknown;
}

/**
 * The registry max-width for a collapse key, or `null` when the host
 * registry has no device entry for it (the renderers then fall back to
 * the 600px default too).
 */
function collapseMaxWidth(key: string): number | null {
    const maxWidth = getResponsiveRegistry().maxWidth(key);

    return maxWidth !== null && maxWidth > 0 ? maxWidth : null;
}

/**
 * Options for the "Collapse below" select. Keys the host registry
 * doesn't define are left out.
 *
 * @since 1.15.2
 */
export function collapseBreakpointOptions(): Array<{ label: string; value: string }> {
    const options: Array<{ label: string; value: string }> = [
        { label: __('Default (600px)', TEXT_DOMAIN), value: '' },
    ];
    const mobile = collapseMaxWidth('mobile');
    const tablet = collapseMaxWidth('tablet');

    if (mobile !== null) {
        options.push({
            /* translators: %d: the Mobile breakpoint's max-width in px. */
            label: sprintf(__('Mobile (≤%dpx)', TEXT_DOMAIN), mobile),
            value: 'mobile',
        });
    }

    if (tablet !== null) {
        options.push({
            /* translators: %d: the Tablet breakpoint's max-width in px. */
            label: sprintf(__('Tablet (≤%dpx)', TEXT_DOMAIN), tablet),
            value: 'tablet',
        });
    }

    return options;
}

export const withNavigationCollapseControl = createHigherOrderComponent(
    (BlockEdit: ComponentType<BlockEditProps>) => {
        function NavigationCollapseBlockEdit(props: BlockEditProps): JSX.Element {
            const overlayMenu =
                typeof props.attributes?.overlayMenu === 'string' ? props.attributes.overlayMenu : 'mobile';

            if (props.name !== 'core/navigation' || overlayMenu !== 'mobile') {
                return <BlockEdit {...props} />;
            }

            const value =
                typeof props.attributes.collapseBreakpoint === 'string' ? props.attributes.collapseBreakpoint : '';

            return (
                <>
                    <BlockEdit {...props} />
                    <InspectorControls>
                        <PanelBody title={__('Responsive', TEXT_DOMAIN)} initialOpen>
                            <SelectControl
                                __next40pxDefaultSize
                                __nextHasNoMarginBottom
                                label={__('Collapse below', TEXT_DOMAIN)}
                                help={__(
                                    'The width at and below which the menu collapses behind the open button.',
                                    TEXT_DOMAIN
                                )}
                                value={value}
                                options={collapseBreakpointOptions()}
                                onChange={(next: string) =>
                                    props.setAttributes({ collapseBreakpoint: next === '' ? undefined : next })
                                }
                            />
                        </PanelBody>
                    </InspectorControls>
                </>
            );
        }

        NavigationCollapseBlockEdit.displayName = 'NavigationCollapseBlockEdit';

        return NavigationCollapseBlockEdit;
    },
    'withNavigationCollapseControl'
);

export const withNavigationCollapseCanvas = createHigherOrderComponent(
    (BlockListBlock: ComponentType<BlockListBlockProps>) => {
        function NavigationCollapseListBlock(props: BlockListBlockProps): JSX.Element {
            const key = props.name === 'core/navigation' ? navigationCollapseKey(props.attributes) : null;
            const maxWidth = key === null ? null : collapseMaxWidth(key);
            const active = key !== null && maxWidth !== null;
            const { wrapperProps } = props;

            const css = active ? navigationCollapseCss(key, maxWidth) : '';

            useCanvasScopedStyle(NAVIGATION_COLLAPSE_STYLE_CHANNEL, props.clientId ?? '', css);

            const nextWrapperProps = useMemo(() => {
                if (!active) {
                    return wrapperProps;
                }

                const collapseClass = `${NAVIGATION_COLLAPSE_CLASS_PREFIX}${key}`;
                const existingClass = typeof wrapperProps?.className === 'string' ? wrapperProps.className : '';

                return {
                    ...wrapperProps,
                    className: existingClass === '' ? collapseClass : `${existingClass} ${collapseClass}`,
                };
            }, [active, key, wrapperProps]);

            if (!active) {
                return <BlockListBlock {...props} />;
            }

            return <BlockListBlock {...props} wrapperProps={nextWrapperProps} />;
        }

        NavigationCollapseListBlock.displayName = 'NavigationCollapseListBlock';

        return NavigationCollapseListBlock;
    },
    'withNavigationCollapseCanvas'
);

/**
 * Install the inspector control and canvas preview filters. Idempotent.
 *
 * @since 1.15.2
 */
export function registerNavigationCollapseBreakpoint(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY] === true) {
        return;
    }

    addFilter('editor.BlockEdit', PANEL_NAMESPACE, withNavigationCollapseControl);
    addFilter('editor.BlockListBlock', CANVAS_NAMESPACE, withNavigationCollapseCanvas);
    host[REGISTERED_KEY] = true;
}
