/**
 * Tests for the `artisanpack/icon` edit component's link and
 * accessibility controls (#1022).
 */

import { describe, it, expect, vi } from 'vitest';
import { fireEvent, render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    BlockVerticalAlignmentToolbar: () => null,
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    PanelColorSettings: () => null,
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
}));

vi.mock('@wordpress/components', () => {
    const Passthrough = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>;
    return {
        Button: ({ children, onClick }: { children?: React.ReactNode; onClick?: () => void }) => (
            <button onClick={onClick}>{children}</button>
        ),
        PanelBody: ({ title, children }: { title: string; children?: React.ReactNode }) => (
            <section aria-label={title}>{children}</section>
        ),
        PanelRow: Passthrough,
        TextControl: ({
            label,
            value,
            onChange,
        }: {
            label: string;
            value: string;
            onChange: (value: string) => void;
        }) => (
            <input aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} />
        ),
        ToggleControl: ({
            label,
            checked,
            onChange,
        }: {
            label: string;
            checked: boolean;
            onChange: (value: boolean) => void;
        }) => (
            <input
                type="checkbox"
                aria-label={label}
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
        ),
        __experimentalNumberControl: () => null,
        __experimentalUnitControl: () => null,
    };
});

vi.mock('../custom-svg', () => ({ sanitizeOnServer: vi.fn() }));
vi.mock('../custom-svg-control', () => ({ default: () => null }));
vi.mock('../icon-picker', () => ({ default: () => null }));
vi.mock('../fetch-svg', () => ({ fetchIconSvg: () => new Promise(() => {}) }));

vi.mock('../../_shared/link-toolbar-control', () => ({
    default: ({
        url,
        linkTarget,
        rel,
        onChange,
        onRemove,
    }: {
        url: string;
        linkTarget: string;
        rel: string;
        onChange: (next: { url: string; linkTarget: string | undefined; rel: string | undefined }) => void;
        onRemove: () => void;
    }) => (
        <div data-testid="link-toolbar" data-url={url} data-target={linkTarget} data-rel={rel}>
            <button
                data-testid="set-link"
                onClick={() =>
                    onChange({ url: 'https://example.com', linkTarget: '_blank', rel: 'noreferrer noopener' })
                }
            />
            <button
                data-testid="set-same-tab-link"
                onClick={() => onChange({ url: 'https://example.com', linkTarget: undefined, rel: undefined })}
            />
            <button data-testid="remove-link" onClick={onRemove} />
        </div>
    ),
}));

(globalThis as { React?: unknown }).React = require('react');

import IconEdit from '../edit';
import type { IconAttributes } from '../types';

const ICON = { set: 'fa', name: 'house' };

function renderEdit(attributes: Partial<IconAttributes>, setAttributes = vi.fn()) {
    return {
        setAttributes,
        ...render(<IconEdit attributes={attributes as IconAttributes} setAttributes={setAttributes} />),
    };
}

describe('IconEdit link controls', () => {
    it('passes the saved link to the toolbar control', () => {
        const { getByTestId } = renderEdit({
            iconRef: ICON,
            link: 'https://example.com',
            linkTarget: '_blank',
            linkRel: 'nofollow',
        });
        const toolbar = getByTestId('link-toolbar');
        expect(toolbar.dataset.url).toBe('https://example.com');
        expect(toolbar.dataset.target).toBe('_blank');
        expect(toolbar.dataset.rel).toBe('nofollow');
    });

    it('writes link, linkTarget and linkRel from the toolbar', () => {
        const { getByTestId, setAttributes } = renderEdit({ iconRef: ICON });
        fireEvent.click(getByTestId('set-link'));
        expect(setAttributes).toHaveBeenCalledWith({
            link: 'https://example.com',
            linkTarget: '_blank',
            linkRel: 'noreferrer noopener',
        });
    });

    it('stores empty strings rather than undefined for a same-tab link', () => {
        const { getByTestId, setAttributes } = renderEdit({ iconRef: ICON });
        fireEvent.click(getByTestId('set-same-tab-link'));
        expect(setAttributes).toHaveBeenCalledWith({
            link: 'https://example.com',
            linkTarget: '',
            linkRel: '',
        });
    });

    it('clears the link on unlink', () => {
        const { getByTestId, setAttributes } = renderEdit({
            iconRef: ICON,
            link: 'https://example.com',
            linkTarget: '_blank',
        });
        fireEvent.click(getByTestId('remove-link'));
        expect(setAttributes).toHaveBeenCalledWith({ link: '', linkTarget: '', linkRel: '' });
    });

    it('shows the link relation field only for a linked icon', () => {
        const { queryByLabelText, rerender, setAttributes } = renderEdit({ iconRef: ICON });
        expect(queryByLabelText('Link relation')).toBeNull();

        rerender(
            <IconEdit
                attributes={{ iconRef: ICON, link: 'https://example.com', linkRel: 'sponsored' } as IconAttributes}
                setAttributes={setAttributes}
            />
        );
        const rel = queryByLabelText('Link relation') as HTMLInputElement;
        expect(rel.value).toBe('sponsored');
        fireEvent.change(rel, { target: { value: 'nofollow' } });
        expect(setAttributes).toHaveBeenCalledWith({ linkRel: 'nofollow' });
    });
});

describe('IconEdit accessibility controls', () => {
    it('edits the accessible label, title and decorative flag', () => {
        const { getByLabelText, setAttributes } = renderEdit({ iconRef: ICON });

        fireEvent.change(getByLabelText('Accessible label'), { target: { value: 'Home' } });
        expect(setAttributes).toHaveBeenCalledWith({ ariaLabel: 'Home' });

        fireEvent.change(getByLabelText('Title attribute'), { target: { value: 'Go home' } });
        expect(setAttributes).toHaveBeenCalledWith({ titleAttr: 'Go home' });

        fireEvent.click(getByLabelText('Decorative'));
        expect(setAttributes).toHaveBeenCalledWith({ isDecorative: true });
    });

    it('warns when a decorative linked icon has no label', () => {
        const { getByRole } = renderEdit({
            iconRef: ICON,
            link: 'https://example.com',
            isDecorative: true,
        });
        expect(getByRole('alert').textContent).toContain('needs an aria-label');
    });
});
