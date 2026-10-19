/**
 * Tests for the Visibility panel's Screen size subsection (#974): the
 * device keys (Mobile / Tablet) and the Desktop shortcut are offered,
 * and legacy min-width keys in saved content still render and toggle.
 */

import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

vi.mock('@wordpress/components', () => {
    const Passthrough = ({ children }: { children?: ReactNode }) => <div>{children}</div>;
    const Checkbox = ({ label, help, checked, onChange }: { label: string; help?: string; checked?: boolean; onChange: (checked: boolean) => void }) => (
        <label>
            <input type="checkbox" checked={checked === true} onChange={(event) => onChange(event.target.checked)} />
            {label}
            {help ? <small>{help}</small> : null}
        </label>
    );

    return {
        BaseControl: Passthrough,
        Button: Passthrough,
        ButtonGroup: Passthrough,
        CheckboxControl: Checkbox,
        PanelBody: Passthrough,
        PanelRow: Passthrough,
        SelectControl: () => null,
        TextControl: () => null,
        ToggleControl: Checkbox,
    };
});

import { groupScreenSizeBreakpoints, VisibilityPanel } from '../VisibilityPanel';
import type { VisibilityAttribute, VisibilityBreakpointOption } from '../types';

const OPTIONS: VisibilityBreakpointOption[] = [
    { key: 'tablet', label: 'Tablet', maxWidthPx: 1023 },
    { key: 'mobile', label: 'Mobile', maxWidthPx: 767 },
    { key: 'sm', label: 'Mobile', minWidthPx: 640 },
    { key: 'md', label: 'Tablet', minWidthPx: 768 },
    { key: 'lg', label: 'Desktop', minWidthPx: 1024 },
    { key: 'xl', label: 'xl+', minWidthPx: 1280 },
    { key: '2xl', label: '2xl+', minWidthPx: 1536 },
];

function renderPanel(value: VisibilityAttribute | null, options = OPTIONS) {
    const onChange = vi.fn();

    render(
        <VisibilityPanel
            value={value}
            onChange={onChange}
            breakpointOptions={options}
            roleOptions={[]}
            searchUsers={async () => []}
        />,
    );

    return onChange;
}

function checkbox(label: string): HTMLInputElement {
    return screen.getByText(label, { exact: true, selector: 'label' }).querySelector('input') as HTMLInputElement;
}

describe('groupScreenSizeBreakpoints', () => {
    it('groups devices, the desktop shortcut and legacy ranges', () => {
        const groups = groupScreenSizeBreakpoints(OPTIONS);

        expect(groups.devices.map((bp) => bp.key)).toEqual(['mobile', 'tablet']);
        expect(groups.desktopKeys).toEqual(['lg', 'xl', '2xl']);
        expect(groups.desktopMinWidthPx).toBe(1024);
        expect(groups.legacy.map((bp) => bp.rangeLabel)).toEqual([
            '640–767px', '768–1023px', '1024–1279px', '1280–1535px', '≥1536px',
        ]);
    });

    it('drops the desktop shortcut when no legacy key starts right above the widest device', () => {
        const groups = groupScreenSizeBreakpoints(OPTIONS.map((bp) => (bp.key === 'tablet' ? { ...bp, maxWidthPx: 999 } : bp)));

        expect(groups.desktopMinWidthPx).toBeNull();
        expect(groups.desktopKeys).toEqual([]);
    });
});

describe('VisibilityPanel screen size', () => {
    const empty: VisibilityAttribute = { screenSize: { direction: 'hide', breakpoints: [] } };

    it('offers Mobile, Tablet and Desktop and hides the legacy ranges by default', () => {
        renderPanel(empty);

        expect(checkbox('Mobile (≤767px)')).toBeTruthy();
        expect(checkbox('Tablet (≤1023px)')).toBeTruthy();
        expect(checkbox('Desktop (≥1024px)')).toBeTruthy();
        expect(screen.queryByText('Legacy width ranges:')).toBeNull();
    });

    it('writes the mobile key', () => {
        const onChange = renderPanel(empty);

        fireEvent.click(checkbox('Mobile (≤767px)'));

        expect(onChange).toHaveBeenCalledWith({ screenSize: { direction: 'hide', breakpoints: ['mobile'] } });
    });

    it('writes the tablet key', () => {
        const onChange = renderPanel(empty);

        fireEvent.click(checkbox('Tablet (≤1023px)'));

        expect(onChange).toHaveBeenCalledWith({ screenSize: { direction: 'hide', breakpoints: ['tablet'] } });
    });

    it('writes Desktop as every legacy key from 1024px up', () => {
        const onChange = renderPanel(empty);

        fireEvent.click(checkbox('Desktop (≥1024px)'));

        expect(onChange).toHaveBeenCalledWith({ screenSize: { direction: 'hide', breakpoints: ['lg', 'xl', '2xl'] } });
    });

    it('shows Desktop checked without legacy ranges when saved content covers it', () => {
        renderPanel({ screenSize: { direction: 'hide', breakpoints: ['mobile', 'lg', 'xl', '2xl'] } });

        expect(checkbox('Mobile (≤767px)').checked).toBe(true);
        expect(checkbox('Desktop (≥1024px)').checked).toBe(true);
        expect(screen.queryByText('Legacy width ranges:')).toBeNull();
    });

    it('renders legacy keys from saved content so they can be unchecked', () => {
        const onChange = renderPanel({ screenSize: { direction: 'hide', breakpoints: ['md'] } });

        expect(screen.getByText('Legacy width ranges:')).toBeTruthy();
        expect(checkbox('768–1023px').checked).toBe(true);
        expect(checkbox('Desktop (≥1024px)').checked).toBe(false);

        fireEvent.click(checkbox('768–1023px'));

        expect(onChange).toHaveBeenCalledWith({ screenSize: { direction: 'hide', breakpoints: [] } });
    });

    it('falls back to the legacy ranges when no device keys are registered', () => {
        renderPanel(empty, OPTIONS.filter((bp) => typeof bp.minWidthPx === 'number'));

        expect(screen.queryByText('Desktop (≥1024px)')).toBeNull();
        expect(checkbox('640–767px')).toBeTruthy();
    });
});
