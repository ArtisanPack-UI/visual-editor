/**
 * FontPreview — RTL test (#635).
 *
 * Verifies editable sample text, session-scoped `@font-face` injection from
 * installed faces, provider preview-stylesheet linking, and teardown on
 * unmount.
 *
 * @since 1.7.0
 */

import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (s: string) => s,
}));

vi.mock('../../vendor/i18n', () => ({ TEXT_DOMAIN: 'artisanpack-visual-editor' }));

import FontPreview from '../font-preview';
import type { FontFace } from '../api-client';

const face: FontFace = {
    id: 1,
    weight: 400,
    style: 'normal',
    format: 'woff2',
    axes: null,
    url: 'https://example.test/inter-400.woff2',
};

describe('FontPreview', () => {
    it('renders an editable textarea and reports edits', () => {
        const onChange = vi.fn();
        render(<FontPreview family="Inter" sampleText="Hello" onSampleTextChange={onChange} />);

        const field = screen.getByRole('textbox');
        expect(field).toHaveValue('Hello');

        fireEvent.change(field, { target: { value: 'World' } });
        expect(onChange).toHaveBeenCalledWith('World');
    });

    it('renders a static preview when no change handler is given', () => {
        render(<FontPreview family="Inter" sampleText="Static" />);

        expect(screen.queryByRole('textbox')).toBeNull();
        expect(screen.getByText('Static')).toBeInTheDocument();
    });

    it('injects a scoped @font-face for installed faces and removes it on unmount', () => {
        const { unmount } = render(
            <FontPreview family="Inter" faces={[face]} sampleText="Hi" />
        );

        const style = document.head.querySelector('style[data-font-preview="Inter"]');
        expect(style).not.toBeNull();
        expect(style?.textContent).toContain('font-family: "Inter"');
        expect(style?.textContent).toContain(face.url);
        expect(style?.textContent).toContain('format("woff2")');

        unmount();
        expect(document.head.querySelector('style[data-font-preview="Inter"]')).toBeNull();
    });

    it('links a provider preview stylesheet and removes it on unmount', () => {
        const { unmount } = render(
            <FontPreview family="Roboto" previewUrl="https://example.test/roboto.css" sampleText="Hi" />
        );

        const link = document.head.querySelector('link[data-font-preview-provider="Roboto"]');
        expect(link).not.toBeNull();
        expect(link?.getAttribute('href')).toBe('https://example.test/roboto.css');

        unmount();
        expect(document.head.querySelector('link[data-font-preview-provider="Roboto"]')).toBeNull();
    });

    it('escapes backslashes and quotes in the family name to prevent CSS injection', () => {
        // A backslash could otherwise consume the closing quote and break out
        // of the @font-face block; a quote could terminate the string early.
        render(<FontPreview family={'Evil\\"; } body{display:none}'} faces={[face]} sampleText="Hi" />);

        const style = document.head.querySelector('style[data-font-preview]');
        expect(style?.textContent).toContain('Evil\\\\\\"');
        // The injected rule must not contain an unescaped `"; }` breakout.
        expect(style?.textContent).not.toContain('Evil\\"; }');
    });

    it('neutralizes a form-feed in the family name', () => {
        // U+000C would otherwise be normalized to a newline and terminate the
        // CSS string, letting the rest escape the @font-face block.
        render(<FontPreview family={'A\f} body{display:none}'} faces={[face]} sampleText="Hi" />);

        const style = document.head.querySelector('style[data-font-preview]');
        // The form feed is collapsed to a space, so the payload stays inside
        // the quoted family value and cannot start a new rule.
        expect(style?.textContent).toContain('A } body{display:none}');
    });

    it('constrains the weight and style to safe tokens', () => {
        render(
            <FontPreview
                family="Inter"
                faces={[{ ...face, weight: 400.9, style: 'oblique' as unknown as string }]}
                sampleText="Hi"
            />
        );

        const style = document.head.querySelector('style[data-font-preview="Inter"]');
        expect(style?.textContent).toContain('font-weight: 400;');
        expect(style?.textContent).toContain('font-style: normal;');
    });

    it('skips faces without a resolvable URL', () => {
        render(
            <FontPreview
                family="Nope"
                faces={[{ ...face, url: null }]}
                sampleText="Hi"
            />
        );

        expect(document.head.querySelector('style[data-font-preview="Nope"]')).toBeNull();
    });
});
