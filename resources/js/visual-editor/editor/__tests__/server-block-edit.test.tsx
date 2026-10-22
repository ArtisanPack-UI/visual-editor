/**
 * Tests for the generic server-block edit component and the synthesized
 * inspector controls it renders (#766), including the bound-attribute lock
 * (#995): an attribute connected to a binding source renders its control
 * disabled with help text pointing at the Block bindings panel, while every
 * unbound attribute stays fully editable.
 *
 * `@wordpress/components` is stubbed with plain DOM controls that surface the
 * `disabled` / `help` props so the assertions don't depend on the real
 * component library's markup.
 */

import { describe, it, expect, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    sprintf: (format: string, ...args: unknown[]) => {
        let index = 0;
        return format
            .replace(/%(\d+)\$s/g, (_match, position: string) => String(args[Number(position) - 1]))
            .replace(/%s/g, () => String(args[index++]));
    },
}));

vi.mock('../../vendor/i18n', () => ({ TEXT_DOMAIN: 'artisanpack-visual-editor' }));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children: ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: () => ({ 'data-testid': 'block-props' }),
}));

vi.mock('../server-side-render', () => ({
    ServerSideRender: () => <div data-testid="ssr" />,
}));

interface StubProps {
    label: string;
    help?: string;
    disabled?: boolean;
    value?: unknown;
    checked?: boolean;
    options?: Array<{ label: string; value: string }>;
    onChange: (value: unknown) => void;
}

function stubInput(kind: string) {
    return function Stub({ label, help, disabled, value, onChange }: StubProps) {
        return (
            <input
                data-testid={`${kind}:${label}`}
                data-help={help ?? ''}
                disabled={disabled}
                value={value === undefined ? '' : String(value)}
                onChange={(event) =>
                    onChange(
                        kind === 'range'
                            ? Number(event.target.value)
                            : event.target.value
                    )
                }
            />
        );
    };
}

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: ReactNode }) => <section>{children}</section>,
    TextControl: stubInput('text'),
    TextareaControl: stubInput('textarea'),
    RangeControl: stubInput('range'),
    SelectControl: stubInput('select'),
    ToggleControl: ({ label, help, disabled, checked, onChange }: StubProps) => (
        <input
            type="checkbox"
            data-testid={`toggle:${label}`}
            data-help={help ?? ''}
            disabled={disabled}
            checked={Boolean(checked)}
            onChange={() => onChange(!checked)}
        />
    ),
}));

import { createServerBlockEdit } from '../server-block-edit';
import { AttributeControl, type AttributeControlDescriptor } from '../attribute-controls';
import { BoundAttributesContext } from '../../bindings/bound-attributes-context';

const SCHEMA = {
    title: { type: 'string' },
    lines: { type: 'string', apControl: { control: 'textarea' } },
    count: { type: 'number' },
    size: { type: 'number', apControl: { min: 1, max: 10 } },
    tone: { type: 'string', enum: ['light', 'dark'] },
    showTitle: { type: 'boolean' },
} as const;

const Edit = createServerBlockEdit('artisanpack/terminal', SCHEMA);

function renderEdit(
    setAttributes = vi.fn(),
    bound: Record<string, { source: string; key: string | null }> = {}
) {
    render(
        <BoundAttributesContext.Provider value={bound}>
            <Edit
                attributes={{
                    title: 'Hello',
                    lines: 'ls -la',
                    count: 2,
                    size: 3,
                    tone: 'light',
                    showTitle: false,
                }}
                setAttributes={setAttributes}
            />
        </BoundAttributesContext.Provider>
    );

    return setAttributes;
}

describe('ServerBlockEdit inspector controls', () => {
    it('writes every control kind through setAttributes when unbound', () => {
        const setAttributes = renderEdit();

        fireEvent.change(screen.getByTestId('text:Title'), { target: { value: 'Hi there' } });
        fireEvent.change(screen.getByTestId('textarea:Lines'), { target: { value: 'pwd' } });
        fireEvent.change(screen.getByTestId('text:Count'), { target: { value: '7' } });
        fireEvent.change(screen.getByTestId('range:Size'), { target: { value: '5' } });
        fireEvent.change(screen.getByTestId('select:Tone'), { target: { value: 'dark' } });
        fireEvent.click(screen.getByTestId('toggle:Show Title'));

        expect(setAttributes).toHaveBeenCalledWith({ title: 'Hi there' });
        expect(setAttributes).toHaveBeenCalledWith({ lines: 'pwd' });
        expect(setAttributes).toHaveBeenCalledWith({ count: 7 });
        expect(setAttributes).toHaveBeenCalledWith({ size: 5 });
        expect(setAttributes).toHaveBeenCalledWith({ tone: 'dark' });
        expect(setAttributes).toHaveBeenCalledWith({ showTitle: true });
    });

    it('renders a bound attribute disabled with help naming its source and key', () => {
        const setAttributes = renderEdit(vi.fn(), {
            lines: { source: 'package', key: 'terminal' },
        });

        const lines = screen.getByTestId('textarea:Lines') as HTMLTextAreaElement;
        expect(lines.disabled).toBe(true);
        expect(lines.getAttribute('data-help')).toBe(
            'Connected to package → terminal. Disconnect it in the Block bindings panel to edit.'
        );

        fireEvent.change(lines, { target: { value: 'typed' } });
        expect(setAttributes).not.toHaveBeenCalled();
    });

    it('omits the key from the help text when the binding has none', () => {
        renderEdit(vi.fn(), { title: { source: 'site', key: null } });

        expect(screen.getByTestId('text:Title').getAttribute('data-help')).toBe(
            'Connected to site. Disconnect it in the Block bindings panel to edit.'
        );
    });

    it('leaves unbound attributes editable alongside a bound one', () => {
        const setAttributes = renderEdit(vi.fn(), {
            lines: { source: 'package', key: 'terminal' },
        });

        const title = screen.getByTestId('text:Title') as HTMLInputElement;
        expect(title.disabled).toBe(false);
        expect(title.getAttribute('data-help')).toBe('');

        fireEvent.change(title, { target: { value: 'Still editable' } });
        expect(setAttributes).toHaveBeenCalledWith({ title: 'Still editable' });
    });
});

describe('AttributeControl disabled prop', () => {
    const descriptors: AttributeControlDescriptor[] = [
        { kind: 'text', name: 'a', label: 'A', help: 'own help' },
        { kind: 'textarea', name: 'b', label: 'B', help: 'own help' },
        { kind: 'number', name: 'c', label: 'C', help: 'own help' },
        { kind: 'range', name: 'd', label: 'D', help: 'own help', min: 0, max: 10 },
        {
            kind: 'select',
            name: 'e',
            label: 'E',
            help: 'own help',
            options: [{ label: 'X', value: 'x' }],
        },
        { kind: 'toggle', name: 'f', label: 'F', help: 'own help' },
    ];

    it.each(descriptors.map((descriptor) => [descriptor.kind, descriptor] as const))(
        'disables the %s control, swaps in the disabled help and drops changes',
        (kind, descriptor) => {
            const onChange = vi.fn();
            render(
                <AttributeControl
                    descriptor={descriptor}
                    value={undefined}
                    onChange={onChange}
                    disabled
                    disabledHelp="locked"
                />
            );

            // `number` renders through TextControl with `type="number"`.
            const stub = kind === 'number' ? 'text' : kind;
            const control = screen.getByTestId(`${stub}:${descriptor.label}`) as HTMLInputElement;
            expect(control.disabled).toBe(true);
            expect(control.getAttribute('data-help')).toBe('locked');

            if (kind === 'toggle') {
                fireEvent.click(control);
            } else {
                fireEvent.change(control, { target: { value: '4' } });
            }
            expect(onChange).not.toHaveBeenCalled();
        }
    );

    it('keeps the descriptor help and stays enabled by default', () => {
        render(
            <AttributeControl
                descriptor={{ kind: 'text', name: 'a', label: 'A', help: 'own help' }}
                value="v"
                onChange={vi.fn()}
            />
        );

        const control = screen.getByTestId('text:A') as HTMLInputElement;
        expect(control.disabled).toBe(false);
        expect(control.getAttribute('data-help')).toBe('own help');
    });
});
