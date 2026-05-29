/**
 * Tests for the Phase I5 entity-fork edit/save delegation helpers (#413).
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@testing-library/react';

const getBlockType = vi.fn();

vi.mock('@wordpress/blocks', () => ({
    getBlockType: (name: string) => getBlockType(name),
}));

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({ className: 'fallback-wrapper' }),
}));

import { createForkedEntityEdit } from '../forked-entity-edit';
import { createForkedEntitySave } from '../forked-entity-save';
import { createEntityPlaceholderEdit } from '../entity-placeholder-edit';

beforeEach(() => {
    getBlockType.mockReset();
});

describe('createForkedEntityEdit', () => {
    it('delegates to the registered core block edit, forwarding props', () => {
        const CoreEdit = vi.fn(({ label }: { label: string }) => (
            <span data-testid="core-edit">{label}</span>
        ));
        getBlockType.mockReturnValue({ edit: CoreEdit });

        const Edit = createForkedEntityEdit('core/post-title');
        const { getByTestId } = render(<Edit label="hello" attributes={{}} />);

        expect(getBlockType).toHaveBeenCalledWith('core/post-title');
        expect(getByTestId('core-edit').textContent).toBe('hello');
        expect(CoreEdit).toHaveBeenCalled();
    });

    it('falls back to an empty block wrapper when the core block is unregistered', () => {
        getBlockType.mockReturnValue(undefined);

        const Edit = createForkedEntityEdit('core/site-title');
        const { container } = render(<Edit attributes={{}} />);

        expect(container.querySelector('.fallback-wrapper')).not.toBeNull();
    });

    it('sets a descriptive displayName', () => {
        const Edit = createForkedEntityEdit('core/navigation');
        expect(Edit.displayName).toBe('ForkedEntityEdit(core/navigation)');
    });
});

describe('createForkedEntitySave', () => {
    it('delegates serialization to the registered core block save', () => {
        const CoreSave = vi.fn(() => <nav data-testid="core-save" />);
        getBlockType.mockReturnValue({ save: CoreSave });

        const Save = createForkedEntitySave('core/navigation');
        const { getByTestId } = render(<Save attributes={{}} />);

        expect(getBlockType).toHaveBeenCalledWith('core/navigation');
        expect(getByTestId('core-save')).not.toBeNull();
    });

    it('renders nothing when the core block is unregistered', () => {
        getBlockType.mockReturnValue(undefined);

        const Save = createForkedEntitySave('core/navigation');
        const { container } = render(<Save attributes={{}} />);

        expect(container.innerHTML).toBe('');
    });
});

describe('createEntityPlaceholderEdit', () => {
    it('renders the resolved text value when present', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Site Title',
            resolvedKey: '_resolvedSiteTitle',
            kind: 'text',
        });
        const { getByText, queryByText } = render(
            <Edit attributes={{ _resolvedSiteTitle: 'Keystone CMS' }} />
        );

        expect(getByText('Keystone CMS')).not.toBeNull();
        // The label placeholder must not show when a value is resolved.
        expect(queryByText('Site Title')).toBeNull();
    });

    it('renders a labelled placeholder when the value is absent', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Post Author',
            resolvedKey: '_resolvedAuthorName',
            kind: 'text',
        });
        const { getByText } = render(<Edit attributes={{}} />);

        expect(getByText('Post Author')).not.toBeNull();
    });

    it('renders resolved HTML for html-kind blocks', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Post Content',
            resolvedKey: '_resolvedContent',
            kind: 'html',
        });
        const { container } = render(
            <Edit attributes={{ _resolvedContent: '<p>Body copy</p>' }} />
        );

        expect(container.querySelector('p')?.textContent).toBe('Body copy');
    });

    it('renders an image for image-kind blocks', () => {
        const Edit = createEntityPlaceholderEdit({
            label: 'Featured Image',
            resolvedKey: '_resolvedImageUrl',
            kind: 'image',
            altKey: '_resolvedImageAlt',
        });
        const { container } = render(
            <Edit
                attributes={{
                    _resolvedImageUrl: 'https://example.test/a.jpg',
                    _resolvedImageAlt: 'Alt text',
                }}
            />
        );

        const img = container.querySelector('img');
        expect(img?.getAttribute('src')).toBe('https://example.test/a.jpg');
        expect(img?.getAttribute('alt')).toBe('Alt text');
    });
});
