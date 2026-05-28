/**
 * Tests for the `artisanpack/spacer` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/element', () => ({
    useState: <T,>(initial: T): [T, (v: T) => void] => [initial, () => undefined],
    useEffect: () => undefined,
}));

vi.mock('@wordpress/compose', () => ({
    useInstanceId: () => 'spacer-instance-id',
    usePrevious: () => undefined,
}));

vi.mock('@wordpress/data', () => ({
    useSelect: () => false,
    useDispatch: () => ({
        __unstableMarkNextChangeAsNotPersistent: () => undefined,
    }),
}));

vi.mock('@wordpress/primitives', () => ({
    View: ((props: Record<string, unknown>) => (
        <div {...props} />
    )) as unknown as React.FC<Record<string, unknown>>,
}));

vi.mock('@wordpress/components', () => ({
    ResizableBox: ((props: Record<string, unknown>) => (
        <div data-testid="resizable" {...props} />
    )) as unknown as React.FC<Record<string, unknown>>,
    __experimentalUseCustomUnits: () => [],
    __experimentalUnitControl: () => null,
    __experimentalParseQuantityAndUnitFromRawValue: () => [undefined, 'px'],
    __experimentalToolsPanel: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="tools-panel">{children}</div>
    ),
    __experimentalToolsPanelItem: ({
        children,
    }: {
        children: React.ReactNode;
    }) => <div>{children}</div>,
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    getSpacingPresetCssVar: (value?: string) => value,
    getCustomValueFromPreset: (value?: string) => value,
    store: { name: 'core/block-editor' },
    privateApis: { useSpacingSizes: () => [] },
    useBlockEditingMode: () => 'default',
    useSettings: () => [undefined],
    __experimentalSpacingSizesControl: () => null,
    isValueSpacingPreset: () => false,
}));

(globalThis as { React?: unknown }).React = require('react');

import SpacerEdit from '../edit';

describe('SpacerEdit', () => {
    it('renders a div wrapper with the wp-block-spacer class', () => {
        const { container } = render(
            <SpacerEdit
                attributes={{ height: '100px' }}
                isSelected={false}
                setAttributes={vi.fn()}
                toggleSelection={vi.fn()}
                context={{ orientation: undefined }}
            />
        );
        expect(
            container.querySelector('div[class*="wp-block-spacer"]')
        ).toBeTruthy();
    });

    it('mounts inspector controls in vertical (non-flex) layouts', () => {
        const { getByTestId } = render(
            <SpacerEdit
                attributes={{ height: '100px' }}
                isSelected={false}
                setAttributes={vi.fn()}
                toggleSelection={vi.fn()}
                context={{ orientation: undefined }}
            />
        );
        expect(getByTestId('inspector')).toBeTruthy();
    });
});
