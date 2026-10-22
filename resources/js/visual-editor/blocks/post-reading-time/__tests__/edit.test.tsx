/**
 * Post Reading Time editor preview (#943): stamped value, then the query
 * loop's preview post, then a sample line.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({ className: 'preview-wrapper' }),
    InspectorControls: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    TextControl: () => null,
}));

import PostReadingTimeEdit from '../edit';
import { PREVIEW_CONTEXT_KEY } from '../../_shared/entity-placeholder-edit';

describe('PostReadingTimeEdit', () => {
    it('previews the stamped minutes with the prefix and suffix', () => {
        const { getByText } = render(
            <PostReadingTimeEdit
                attributes={{ prefix: 'About ', suffix: 'minutes', _resolvedReadingTime: 4 }}
                context={{}}
            />
        );
        expect(getByText('About 4 minutes')).not.toBeNull();
    });

    it('previews the query loop post reading time', () => {
        const { getByText } = render(
            <PostReadingTimeEdit
                attributes={{}}
                context={{ [PREVIEW_CONTEXT_KEY]: { id: 1, readingTime: 3 } }}
            />
        );
        expect(getByText('3 min read')).not.toBeNull();
    });

    it('shows a sample line with no post in scope', () => {
        const { getByText } = render(<PostReadingTimeEdit attributes={{}} context={{}} />);
        expect(getByText('6 min read')).not.toBeNull();
    });
});
