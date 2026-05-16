/**
 * Shared `BlockEditorProvider` boundary for the site editor — #436.
 *
 * `@wordpress/block-editor` scopes its data store per
 * `<BlockEditorProvider>` instance. The site-editor sections used to
 * mount the provider *inside* their canvas component
 * (`EntityEditorCanvas`, `PatternCanvas`), so the inspector — rendered
 * as a sibling of the canvas — fell outside the provider's registry
 * scope and `select('core/block-editor').getSelectedBlockClientId()`
 * always read null. The Block tab of the inspector was permanently
 * stuck on "Click on a block to view its settings."
 *
 * Hoisting the provider here, above *both* the canvas and the
 * inspector slots, gives them a single shared registry — matching how
 * the post editor (`editor/editor-app.tsx`) has always wired it. React
 * portals preserve context, so the lazy sections (patterns) can keep
 * portaling their canvas and inspector into separate DOM slots as long
 * as both portals are React-descendants of this boundary.
 *
 * `Popover.Slot` and `ConvertToPatternControl` live inside the
 * provider — they were previously inside each canvas's own provider;
 * they move here with it.
 */

import {
    BlockEditorProvider,
} from '@wordpress/block-editor';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { useMemo } from 'react';
// `@wordpress/format-library` is a side-effect import: it registers the
// core rich-text formats (bold, italic, link, …) so the block toolbar's
// inline formatting controls work inside RichText blocks. It lived in
// the canvas components before the provider was hoisted; it follows the
// provider here.
import '@wordpress/format-library';
import { type ReactNode } from 'react';

import { ConvertToPatternControl } from '../editor/convert-to-pattern-control';
import { editorSettings } from '../editor-settings';
import { useThemeGlobalStylesCss } from './use-theme-global-styles-css';

// Gutenberg editor-surface stylesheets. Previously imported by each
// canvas component; they follow the provider here so the boundary owns
// the editor surface end-to-end and the canvas components stay style-
// free presentational shells.
import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

export interface BlockEditorBoundaryProps {
    /** Parsed block tree for the active entity. */
    blocks: readonly unknown[];
    onChange: (blocks: readonly unknown[]) => void;
    onInput: (blocks: readonly unknown[]) => void;
    /**
     * API base for the "Convert to pattern" control. Omitted (or empty)
     * when the section doesn't surface the control.
     */
    apiBase?: string;
    /**
     * Test-only override for the active theme's compiled CSS. In
     * production the boundary fetches this through
     * {@see useThemeGlobalStylesCss} keyed on `apiBase`; tests pass an
     * explicit string to avoid hitting the network. When `undefined`
     * the hook drives the value (Keystone #47).
     */
    themeGlobalStylesCss?: string;
    /**
     * Canvas and inspector slots. Both must be rendered as children so
     * they share this boundary's `core/block-editor` registry — even
     * when a section portals them into separate DOM nodes.
     */
    children: ReactNode;
}

export function BlockEditorBoundary(props: BlockEditorBoundaryProps): JSX.Element {
    const { blocks, onChange, onInput, apiBase, themeGlobalStylesCss, children } = props;

    // Tests can short-circuit the network by passing a string directly;
    // production drives the value through the hook keyed on `apiBase`.
    const fetchedCss = useThemeGlobalStylesCss(apiBase);
    const themeCss = themeGlobalStylesCss !== undefined ? themeGlobalStylesCss : fetchedCss;

    // Append the theme's compiled global-styles CSS to the editor's
    // `styles` array so Gutenberg cascades it into the canvas. Memoized
    // so identity-stable `editorSettings.styles` doesn't bust the
    // provider's effects on every parent re-render. When no theme CSS
    // is available we hand the provider the original `editorSettings`
    // object — no allocation, no diff.
    const settings = useMemo(() => {
        if (themeCss === undefined || themeCss === '') {
            return editorSettings;
        }

        return {
            ...editorSettings,
            styles: [
                ...editorSettings.styles,
                { css: themeCss },
            ],
        };
    }, [themeCss]);

    return (
        <SlotFillProvider>
            <BlockEditorProvider
                value={blocks}
                settings={settings}
                onChange={onChange}
                onInput={onInput}
            >
                {children}
                <Popover.Slot />
                {apiBase !== undefined && apiBase !== '' ? (
                    <ConvertToPatternControl apiBase={apiBase} />
                ) : null}
            </BlockEditorProvider>
        </SlotFillProvider>
    );
}
