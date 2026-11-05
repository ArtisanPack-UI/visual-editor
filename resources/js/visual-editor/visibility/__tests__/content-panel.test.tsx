/**
 * Tests for the Visibility panel's Content subsection (#1030): the
 * source and condition selects, the Any / All and Show / Hide controls,
 * and the detected-source hint.
 */

import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

vi.mock('@wordpress/components', () => {
    const Passthrough = ({ children }: { children?: ReactNode }) => <div>{children}</div>;
    const Checkbox = ({ label, checked, onChange }: { label: string; checked?: boolean; onChange: (checked: boolean) => void }) => (
        <label>
            <input type="checkbox" checked={checked === true} onChange={(event) => onChange(event.target.checked)} />
            {label}
        </label>
    );
    const Select = ({ label, value, options, onChange }: { label: string; value: string; options: Array<{ value: string; label: string }>; onChange: (next: string) => void }) => (
        <label>
            {label}
            <select value={value} onChange={(event) => onChange(event.target.value)}>
                {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
            </select>
        </label>
    );
    const ButtonStub = ({ children, onClick }: { children?: ReactNode; onClick?: () => void }) => <button onClick={onClick}>{children}</button>;

    return {
        BaseControl: Passthrough,
        Button: ButtonStub,
        ButtonGroup: Passthrough,
        CheckboxControl: Checkbox,
        PanelBody: Passthrough,
        PanelRow: Passthrough,
        SelectControl: Select,
        TextControl: () => null,
        ToggleControl: Checkbox,
    };
});

import { VisibilityPanel } from '../VisibilityPanel';
import type { ContentRuleAttrs, VisibilityAttribute, VisibilityContentSourceOption } from '../types';

const SOURCES: VisibilityContentSourceOption[] = [
    {
        key: 'relatedPosts',
        label: 'Related Posts',
        blocks: ['artisanpack/related-posts'],
        conditions: [{ value: 'hasResults', label: 'Has results' }],
    },
    {
        key: 'postNavigation',
        label: 'Post Navigation',
        blocks: ['artisanpack/post-navigation-link'],
        conditions: [
            { value: 'hasEither', label: 'Has a previous or next post' },
            { value: 'hasNext', label: 'Has a next post' },
        ],
    },
    {
        key: 'products',
        label: 'Products',
        blocks: [],
        conditions: [{ value: 'inStock', label: 'In stock' }],
    },
];

const RELATED: ContentRuleAttrs = { source: 'relatedPosts', condition: 'hasResults', match: 'any', direction: 'show' };

function renderPanel(content: ContentRuleAttrs | undefined, subtreeBlockNames: string[] = [], sources = SOURCES) {
    const onChange = vi.fn();
    const value: VisibilityAttribute | null = content === undefined ? null : { content };

    render(
        <VisibilityPanel
            value={value}
            onChange={onChange}
            breakpointOptions={[]}
            roleOptions={[]}
            searchUsers={async () => []}
            contentSources={sources}
            subtreeBlockNames={subtreeBlockNames}
        />,
    );

    return onChange;
}

function select(label: string): HTMLSelectElement {
    return screen.getByLabelText(label) as HTMLSelectElement;
}

function optionLabels(label: string): string[] {
    return Array.from(select(label).options).map((option) => option.text);
}

describe('Content subsection', () => {
    it('writes the default rule when switched on', () => {
        const onChange = renderPanel(undefined);

        fireEvent.click(screen.getByLabelText('Content'));

        expect(onChange).toHaveBeenCalledWith({
            content: { direction: 'show', match: 'any', source: 'relatedPosts', condition: 'hasResults' },
        });
    });

    it('removes the rule when switched off', () => {
        const onChange = renderPanel(RELATED);

        fireEvent.click(screen.getByLabelText('Content'));

        expect(onChange).toHaveBeenCalledWith(null);
    });

    it('is not offered when there are no sources', () => {
        renderPanel(undefined, [], []);

        expect(screen.queryByLabelText('Content')).toBeNull();
    });

    it('filters the conditions by source', () => {
        renderPanel({ ...RELATED, source: 'postNavigation', condition: 'hasNext' });

        expect(optionLabels('Condition')).toEqual(['Has a previous or next post', 'Has a next post']);
    });

    it('resets the condition when the source changes', () => {
        const onChange = renderPanel(RELATED);

        fireEvent.change(select('Source'), { target: { value: 'postNavigation' } });

        expect(onChange).toHaveBeenCalledWith({
            content: { ...RELATED, source: 'postNavigation', condition: 'hasEither' },
        });
    });

    it('writes the match and direction', () => {
        const onChange = renderPanel(RELATED);

        fireEvent.click(screen.getByText('All'));
        expect(onChange).toHaveBeenLastCalledWith({ content: { ...RELATED, match: 'all' } });

        fireEvent.click(screen.getByText('Hide when'));
        expect(onChange).toHaveBeenLastCalledWith({ content: { ...RELATED, direction: 'hide' } });
    });

    it('keeps a saved source that is no longer registered selected', () => {
        renderPanel({ ...RELATED, source: 'gone', condition: 'stale' });

        expect(select('Source').value).toBe('gone');
        expect(select('Condition').value).toBe('stale');
    });

    it('names what it found in the block', () => {
        renderPanel(RELATED, ['artisanpack/group', 'artisanpack/related-posts']);

        expect(screen.getByRole('status').textContent).toBe('Checks 1 Related Posts block.');
    });

    it('counts several matches', () => {
        renderPanel(RELATED, ['artisanpack/related-posts', 'artisanpack/related-posts']);

        expect(screen.getByRole('status').textContent).toBe('Checks 2 Related Posts blocks.');
    });

    it('warns when nothing matches', () => {
        renderPanel(RELATED, ['artisanpack/group']);

        expect(screen.getByRole('status').textContent).toContain('this rule has no effect');
    });

    it('shows no hint for a source it can\'t detect by name', () => {
        renderPanel({ ...RELATED, source: 'products', condition: 'inStock' }, ['acme/products']);

        expect(screen.getByRole('status').textContent).toBe('');
    });

    it('keeps one live region while the hint changes (#1030)', () => {
        const props = {
            onChange: vi.fn(),
            breakpointOptions: [],
            roleOptions: [],
            searchUsers: async () => [],
            contentSources: SOURCES,
        };
        const { rerender } = render(<VisibilityPanel {...props} value={{ content: RELATED }} subtreeBlockNames={[]} />);
        const region = screen.getByRole('status');

        expect(region.textContent).toContain('this rule has no effect');

        rerender(<VisibilityPanel {...props} value={{ content: RELATED }} subtreeBlockNames={['artisanpack/related-posts']} />);

        expect(screen.getByRole('status')).toBe(region);
        expect(region.textContent).toBe('Checks 1 Related Posts block.');
    });

    it('has the live region in place before the rule is switched on (#1030)', () => {
        const props = {
            onChange: vi.fn(),
            breakpointOptions: [],
            roleOptions: [],
            searchUsers: async () => [],
            contentSources: SOURCES,
        };
        const { rerender } = render(<VisibilityPanel {...props} value={null} subtreeBlockNames={[]} />);
        const region = screen.getByRole('status');

        expect(region.textContent).toBe('');

        rerender(<VisibilityPanel {...props} value={{ content: RELATED }} subtreeBlockNames={[]} />);

        expect(screen.getByRole('status')).toBe(region);
        expect(region.textContent).toContain('this rule has no effect');
    });
});
