import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { PostTitle } from '../post-title';

describe('PostTitle', () => {
    it('renders a label-wrapped textarea with the current value', () => {
        render(<PostTitle value="Hello world" onChange={vi.fn()} />);

        const input = screen.getByTestId(
            'ap-visual-editor-post-title-input'
        ) as HTMLTextAreaElement;

        expect(input.tagName).toBe('TEXTAREA');
        expect(input).toHaveValue('Hello world');
    });

    it('uses the default placeholder when none is provided', () => {
        render(<PostTitle value="" onChange={vi.fn()} />);

        expect(
            screen.getByTestId('ap-visual-editor-post-title-input')
        ).toHaveAttribute('placeholder', 'Add title');
    });

    it('respects a custom placeholder', () => {
        render(
            <PostTitle
                value=""
                onChange={vi.fn()}
                placeholder="Name your recipe"
            />
        );

        expect(
            screen.getByTestId('ap-visual-editor-post-title-input')
        ).toHaveAttribute('placeholder', 'Name your recipe');
    });

    it('fires onChange with the new value on input', () => {
        const onChange = vi.fn();

        render(<PostTitle value="" onChange={onChange} />);

        const input = screen.getByTestId(
            'ap-visual-editor-post-title-input'
        ) as HTMLTextAreaElement;

        fireEvent.change(input, { target: { value: 'A fresh draft' } });

        expect(onChange).toHaveBeenCalledWith('A fresh draft');
    });
});
