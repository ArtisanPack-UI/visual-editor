/**
 * Tests for the `artisanpack/grid-item` edit component.
 */

import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: () => null,
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useInnerBlocksProps: (props?: Record<string, unknown>) => ({ ...props }),
}));

vi.mock('../../_shared/flex-controls/container-controls', () => ({
    FlexContainerControls: () => null,
}));

vi.mock('../../_shared/flex-controls/item-controls', () => ({
    FlexItemControls: () => null,
}));

import GridItemEdit from '../edit';

describe('GridItemEdit', () => {
    it('publishes custom flex values to the canvas (#1039)', () => {
        render(
            <GridItemEdit
                attributes={{
                    innerLayout: 'normal',
                    gridColumnSpan: 1,
                    gridRowSpan: 1,
                    artisanpackFlex: { container: { gap: { row: { base: '3px' } } } },
                }}
                setAttributes={vi.fn()}
                context={{}}
                clientId="grid-item-flex"
            />
        );

        expect(
            document.querySelector('style[data-ap-canvas-styles="flex-arbitrary"]')
                ?.textContent
        ).toContain('row-gap: 3px');
    });
});
