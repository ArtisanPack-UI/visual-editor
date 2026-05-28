/**
 * Tests for the `artisanpack/buttons` edit component.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useInnerBlocksProps: (
        blockProps?: Record<string, unknown>,
        innerProps?: Record<string, unknown>
    ) => ({
        ...blockProps,
        'data-allowed-blocks': JSON.stringify(
            (innerProps as { allowedBlocks?: string[] })?.allowedBlocks ?? []
        ),
        'data-default-block': (
            innerProps as { defaultBlock?: { name?: string } }
        )?.defaultBlock?.name,
    }),
}));

(globalThis as { React?: unknown }).React = require('react');

import ButtonsEdit from '../edit';

describe('ButtonsEdit', () => {
    it('renders a div wrapper with wp-block-buttons', () => {
        const { container } = render(<ButtonsEdit attributes={{}} />);
        expect(
            container.querySelector('div[class*="wp-block-buttons"]')
        ).toBeTruthy();
    });

    it('forces allowedBlocks to artisanpack/button (fork divergence)', () => {
        const { container } = render(<ButtonsEdit attributes={{}} />);
        const wrapper = container.querySelector('div');
        expect(wrapper?.getAttribute('data-allowed-blocks')).toBe(
            JSON.stringify(['artisanpack/button'])
        );
    });

    it('uses artisanpack/button as the default inner block', () => {
        const { container } = render(<ButtonsEdit attributes={{}} />);
        const wrapper = container.querySelector('div');
        expect(wrapper?.getAttribute('data-default-block')).toBe(
            'artisanpack/button'
        );
    });

    it('emits has-custom-font-size when fontSize is set', () => {
        const { container } = render(
            <ButtonsEdit attributes={{ fontSize: 'large' }} />
        );
        expect(
            container.querySelector('div[class*="has-custom-font-size"]')
        ).toBeTruthy();
    });
});
