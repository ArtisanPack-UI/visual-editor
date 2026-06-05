import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const CREATE_MOCK = vi.fn();

vi.mock('../api-client', async () => {
    const actual = await vi.importActual<typeof import('../api-client')>(
        '../api-client'
    );

    return {
        ...actual,
        createEntity: (...args: unknown[]) => CREATE_MOCK(...args),
    };
});

import { TemplateCreateDialog } from '../templates-section';
import { TemplatePartCreateDialog } from '../template-parts-section';

const API_CONFIG = { apiBase: '/visual-editor/api' };

beforeEach(() => {
    CREATE_MOCK.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('TemplateCreateDialog', () => {
    it('renders the kind picker and fallback chain', () => {
        render(
            <TemplateCreateDialog
                apiConfig={API_CONFIG}
                defaultTheme="default"
                onClose={() => undefined}
                onCreated={() => undefined}
            />
        );

        const select = screen.getByTestId(
            'ap-site-editor-new-template-kind'
        ) as HTMLSelectElement;

        expect(select).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-site-editor-new-template-chain')
        ).toBeInTheDocument();
    });

    it('submits the chosen slug + title to the API', async () => {
        CREATE_MOCK.mockResolvedValue({
            id: 42,
            slug: 'page',
            title: { rendered: 'About' },
        });

        const onCreated = vi.fn();
        const user = userEvent.setup();

        render(
            <TemplateCreateDialog
                apiConfig={API_CONFIG}
                defaultTheme="default"
                onClose={() => undefined}
                onCreated={onCreated}
            />
        );

        await user.selectOptions(
            screen.getByTestId('ap-site-editor-new-template-kind'),
            'page'
        );
        await user.type(
            screen.getByTestId('ap-site-editor-new-template-title'),
            'About'
        );
        await user.click(
            screen.getByTestId('ap-site-editor-create-dialog-submit-template')
        );

        await waitFor(() =>
            expect(CREATE_MOCK).toHaveBeenCalledWith(
                API_CONFIG,
                'template',
                expect.objectContaining({
                    slug: 'page',
                    title: 'About',
                    theme: 'default',
                })
            )
        );

        expect(onCreated).toHaveBeenCalledWith(
            expect.objectContaining({ id: 42 })
        );
    });

    it('surfaces server-side validation errors inline', async () => {
        const { SiteEditorApiError } = await vi.importActual<
            typeof import('../api-client')
        >('../api-client');

        CREATE_MOCK.mockRejectedValueOnce(
            new SiteEditorApiError('Invalid', 422, {
                message: 'Invalid',
                errors: { slug: ['Slug in use.'] },
            })
        );

        const user = userEvent.setup();

        render(
            <TemplateCreateDialog
                apiConfig={API_CONFIG}
                defaultTheme="default"
                onClose={() => undefined}
                onCreated={() => undefined}
            />
        );

        // Switch to custom slug so the slug error has a field to bind to.
        await user.selectOptions(
            screen.getByTestId('ap-site-editor-new-template-kind'),
            '__custom__'
        );
        await user.type(
            screen.getByTestId('ap-site-editor-new-template-slug'),
            'existing-slug'
        );
        await user.click(
            screen.getByTestId('ap-site-editor-create-dialog-submit-template')
        );

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-site-editor-create-dialog-error-template')
            ).toBeInTheDocument()
        );

        expect(screen.getByText('Slug in use.')).toBeInTheDocument();
    });
});

describe('TemplatePartCreateDialog', () => {
    it('submits with the chosen area', async () => {
        CREATE_MOCK.mockResolvedValue({
            id: 5,
            slug: 'site-header',
            area: 'header',
            title: { rendered: '' },
        });

        const onCreated = vi.fn();
        const user = userEvent.setup();

        render(
            <TemplatePartCreateDialog
                apiConfig={API_CONFIG}
                defaultTheme="default"
                onClose={() => undefined}
                onCreated={onCreated}
            />
        );

        await user.type(
            screen.getByTestId('ap-site-editor-new-part-slug'),
            'site-header'
        );
        await user.selectOptions(
            screen.getByTestId('ap-site-editor-new-part-area'),
            'footer'
        );
        await user.click(
            screen.getByTestId(
                'ap-site-editor-create-dialog-submit-template-part'
            )
        );

        await waitFor(() =>
            expect(CREATE_MOCK).toHaveBeenCalledWith(
                API_CONFIG,
                'template-part',
                expect.objectContaining({
                    slug: 'site-header',
                    area: 'footer',
                    theme: 'default',
                })
            )
        );
    });
});
