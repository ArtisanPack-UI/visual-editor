import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ComposedViewRibbon } from '../composed-view-ribbon';

describe('ComposedViewRibbon', () => {
    it('names the resolved template', () => {
        render(
            <ComposedViewRibbon templateName="Single Post" templateSlug="single" />
        );

        expect(
            screen.getByText('Editing content inside Single Post')
        ).toBeInTheDocument();
    });

    it('labels itself as a landmark region for assistive tech', () => {
        render(
            <ComposedViewRibbon templateName="Single Post" templateSlug="single" />
        );

        expect(
            screen.getByRole('region', { name: 'Composed-view controls' })
        ).toBeInTheDocument();
    });

    it('deep-links the CTA at the site editor in a new tab', () => {
        render(
            <ComposedViewRibbon templateName="Single Post" templateSlug="single" />
        );

        const cta = screen.getByTestId('ap-composed-view-ribbon-cta');

        expect(cta).toHaveAttribute(
            'href',
            '/visual-editor/site?entity=template&slug=single'
        );
        expect(cta).toHaveAttribute('target', '_blank');
        expect(cta).toHaveAttribute('rel', 'noopener noreferrer');
    });

    it('encodes reserved characters in the slug', () => {
        render(
            <ComposedViewRibbon
                templateName="Odd"
                templateSlug="single/post&more"
            />
        );

        expect(
            screen.getByTestId('ap-composed-view-ribbon-cta')
        ).toHaveAttribute(
            'href',
            '/visual-editor/site?entity=template&slug=single%2Fpost%26more'
        );
    });

    it('honours a host-supplied site-editor route base', () => {
        render(
            <ComposedViewRibbon
                templateName="Single Post"
                templateSlug="single"
                siteEditorRouteBase="/admin/site-editor/"
            />
        );

        expect(
            screen.getByTestId('ap-composed-view-ribbon-cta')
        ).toHaveAttribute(
            'href',
            '/admin/site-editor?entity=template&slug=single'
        );
    });

    it('hides the CTA on the fallback template, keeping the name', () => {
        render(
            <ComposedViewRibbon
                templateName="Default template"
                templateSlug={null}
            />
        );

        expect(
            screen.getByText('Editing content inside Default template')
        ).toBeInTheDocument();
        expect(
            screen.queryByTestId('ap-composed-view-ribbon-cta')
        ).not.toBeInTheDocument();
    });
});
