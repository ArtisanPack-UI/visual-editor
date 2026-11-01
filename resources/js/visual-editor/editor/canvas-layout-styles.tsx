/**
 * Layout styles for the block lists that render in-tree instead of in a
 * `BlockCanvas` iframe: the site editor's entity and pattern canvases
 * (#418).
 *
 * The iframe gets these from `canvasStyles` and from the `EditorStyles`
 * that `BlockCanvas` mounts. Neither reaches an in-tree canvas, so it had
 * no layout baseline (a flex Group computed to `display: block`) and none
 * of Gutenberg's per-container rules (a Group's own Block spacing did
 * nothing) (#1040).
 */

import { __unstableEditorStyles as EditorStyles } from '@wordpress/block-editor';
import { useMemo } from 'react';

import flexLayoutStyles from '../../../css/flex-layout.css?inline';
import { CANVAS_LAYOUT_STYLES } from '../editor-settings';
import { getResponsiveRegistry } from '../responsive/registry';

import { flexLayoutCss } from './flex-layout-css';

/** `EditorStyles` adds the store's overrides to this list. */
const NO_EXTRA_STYLES: readonly { css: string }[] = [];

/**
 * The element margins and layout baseline, then the Flex Layout sheet.
 *
 * The sheet is already in the parent document, but it has to follow the
 * baseline as it does in the iframe: its `:where(.ap-flex) > * + *` reset
 * ties with the baseline's `> * + *` margin, and the later rule wins.
 */
export function CanvasLayoutStyles(): JSX.Element {
    const flexCss = useMemo(() => flexLayoutCss(getResponsiveRegistry()), []);

    return (
        <>
            <style>{CANVAS_LAYOUT_STYLES}</style>
            <style>{flexCss === '' ? flexLayoutStyles : flexCss}</style>
        </>
    );
}

/**
 * Gutenberg's `useStyleOverride` rules. Scoped to the wrapper, so its dark
 * theme check reads the canvas rather than the admin page.
 */
export function CanvasStyleOverrides(): JSX.Element {
    return <EditorStyles styles={NO_EXTRA_STYLES} scope=".editor-styles-wrapper" />;
}
