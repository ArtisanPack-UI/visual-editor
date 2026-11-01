/**
 * The site editor's in-tree canvases get their layout styles from these
 * components, since `canvasStyles` and `EditorStyles` only reach the
 * `BlockCanvas` iframe (#1040).
 */

import { render } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const editorStylesProps = vi.hoisted(() => ({ current: null as Record<string, unknown> | null }));
const flexCss = vi.hoisted(() => ({ current: '' }));

vi.mock('@wordpress/block-editor', () => ({
    __unstableEditorStyles: (props: Record<string, unknown>): null => {
        editorStylesProps.current = props;

        return null;
    },
}));

vi.mock('../flex-layout-css', () => ({
    flexLayoutCss: (): string => flexCss.current,
}));

vi.mock('../../responsive/registry', () => ({
    getResponsiveRegistry: (): Record<string, never> => ({}),
}));

vi.mock('../../editor-settings', () => ({
    CANVAS_LAYOUT_STYLES: '.layout {}',
}));

import { CanvasLayoutStyles, CanvasStyleOverrides } from '../canvas-layout-styles';

describe('CanvasLayoutStyles', () => {
    beforeEach(() => {
        flexCss.current = '';
    });

    it('prints the layout baseline ahead of the Flex Layout sheet', () => {
        flexCss.current = '.flex {}';

        const { container } = render(<CanvasLayoutStyles />);

        expect(Array.from(container.querySelectorAll('style')).map((style) => style.textContent)).toEqual([
            '.layout {}',
            '.flex {}',
        ]);
    });

    it('falls back to the static Flex Layout sheet at the default breakpoints', () => {
        const { container } = render(<CanvasLayoutStyles />);

        // Under vitest (`css: false`), the `?inline` import collapses to an empty string.
        expect(Array.from(container.querySelectorAll('style')).map((style) => style.textContent)).toEqual([
            '.layout {}',
            '',
        ]);
    });
});

describe('CanvasStyleOverrides', () => {
    it('mounts EditorStyles scoped to the canvas wrapper', () => {
        render(<CanvasStyleOverrides />);

        expect(editorStylesProps.current).toEqual({
            styles: [],
            scope: '.editor-styles-wrapper',
        });
    });
});
