/**
 * FontLibraryModal — RTL interaction test (#635).
 *
 * Covers the paths that matter: initial load, tab switching + catalog browse,
 * search, the install flow (pick weights → install → success), bulk uninstall,
 * the GDPR notice on a provider tab, and the read-only gate.
 *
 * @since 1.7.0
 */

import type { ComponentProps, ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

vi.mock('@wordpress/components', () => ({
    Button: ({
        children,
        isDestructive: _isDestructive,
        ...props
    }: ComponentProps<'button'> & { isDestructive?: boolean }) => (
        <button {...props}>{children}</button>
    ),
    Modal: ({ children, title }: { children: ReactNode; title: string }) => (
        <div role="dialog" aria-label={title}>
            {children}
        </div>
    ),
    Notice: ({ children, status }: { children: ReactNode; status?: string }) => (
        <div role="status" data-status={status}>
            {children}
        </div>
    ),
    SearchControl: ({
        value,
        onChange,
        label,
    }: {
        value: string;
        onChange: (v: string) => void;
        label: string;
    }) => (
        <label>
            {label}
            <input aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} />
        </label>
    ),
    Spinner: () => <div>Loading…</div>,
}));

vi.mock('@wordpress/i18n', () => ({
    __: (s: string) => s,
    sprintf: (fmt: string, ...args: unknown[]) => {
        let i = 0;
        return fmt.replace(/%[sd]/g, () => String(args[i++]));
    },
}));

vi.mock('../../vendor/i18n', () => ({ TEXT_DOMAIN: 'artisanpack-visual-editor' }));

const { api, MockApiError } = vi.hoisted(() => {
    class MockApiError extends Error {
        public status: number;
        public code: string | null;
        public constructor(message: string, status: number, code: string | null = null) {
            super(message);
            this.status = status;
            this.code = code;
        }
    }

    return {
        MockApiError,
        api: {
            fetchInstalledFonts: vi.fn(),
            fetchSources: vi.fn(),
            fetchCatalog: vi.fn(),
            installFont: vi.fn(),
            uploadFont: vi.fn(),
            bulkUninstall: vi.fn(),
            uninstallFont: vi.fn(),
        },
    };
});

vi.mock('../api-client', () => ({
    fetchInstalledFonts: (...a: unknown[]) => api.fetchInstalledFonts(...a),
    fetchSources: (...a: unknown[]) => api.fetchSources(...a),
    fetchCatalog: (...a: unknown[]) => api.fetchCatalog(...a),
    installFont: (...a: unknown[]) => api.installFont(...a),
    uploadFont: (...a: unknown[]) => api.uploadFont(...a),
    bulkUninstall: (...a: unknown[]) => api.bulkUninstall(...a),
    uninstallFont: (...a: unknown[]) => api.uninstallFont(...a),
    catalogPreviewUrl: (provider: string, family: { slug: string }) =>
        provider === 'google' ? `https://example.test/${family.slug}.css` : undefined,
    parseVariant: (token: string) =>
        token.endsWith('i')
            ? { weight: parseInt(token, 10) || 400, style: 'italic' }
            : { weight: parseInt(token, 10) || 400, style: 'normal' },
    FontLibraryApiError: MockApiError,
}));

import FontLibraryModal from '../font-library-modal';

const INTER = {
    id: 1,
    provider: 'google',
    family: 'Inter',
    slug: 'inter',
    is_variable: false,
    license: null,
    source_url: null,
    installed_at: null,
    faces: [{ id: 10, weight: 400, style: 'normal', format: 'woff2', axes: null, url: '/inter.woff2' }],
};

const ROBOTO_CATALOG = {
    families: [{ slug: 'roboto', family: 'Roboto', category: null, variants: ['400', '700', '400i'], is_variable: false }],
    page: 1,
    has_more: false,
};

function setup(overrides: Partial<typeof api> = {}, installedReadOnly = false) {
    api.fetchInstalledFonts.mockResolvedValue({
        fonts: [INTER],
        canManage: !installedReadOnly,
        readOnly: installedReadOnly,
    });
    api.fetchSources.mockResolvedValue({
        sources: [
            { key: 'google', label: 'Google Fonts', is_self_hostable: true },
            { key: 'custom', label: 'Custom Upload', is_self_hostable: true },
        ],
        canManage: !installedReadOnly,
        readOnly: installedReadOnly,
    });
    api.fetchCatalog.mockResolvedValue(ROBOTO_CATALOG);
    api.installFont.mockResolvedValue({ ...INTER, id: 2, family: 'Roboto', slug: 'roboto' });
    api.uploadFont.mockResolvedValue({ ...INTER, id: 3, family: 'Brand', slug: 'brand', provider: 'custom' });
    api.bulkUninstall.mockResolvedValue(1);
    api.uninstallFont.mockResolvedValue(undefined);

    Object.assign(api, overrides);
}

describe('FontLibraryModal', () => {
    beforeEach(() => {
        for (const fn of Object.values(api)) {
            (fn as ReturnType<typeof vi.fn>).mockReset?.();
        }
        setup();
    });

    it('renders nothing when closed', () => {
        const { container } = render(<FontLibraryModal isOpen={false} onClose={() => {}} />);
        expect(container).toBeEmptyDOMElement();
    });

    it('loads and lists installed fonts on open', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        expect(await screen.findByText('Inter')).toBeInTheDocument();
        expect(api.fetchInstalledFonts).toHaveBeenCalledTimes(1);
        expect(api.fetchSources).toHaveBeenCalledTimes(1);
    });

    it('shows the provider tab and browses its catalog', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        fireEvent.click(await screen.findByRole('tab', { name: 'Google Fonts' }));

        expect(await screen.findByText('Roboto')).toBeInTheDocument();
        expect(api.fetchCatalog).toHaveBeenCalledWith('google', '', 1, expect.any(AbortSignal));
    });

    it('shows the GDPR self-hosting notice on a provider tab', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        fireEvent.click(await screen.findByRole('tab', { name: 'Google Fonts' }));

        expect(await screen.findByText(/served locally/i)).toBeInTheDocument();
    });

    it('searches the catalog after debounce', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);
        fireEvent.click(await screen.findByRole('tab', { name: 'Google Fonts' }));
        await screen.findByText('Roboto');

        fireEvent.change(screen.getByLabelText('Search Google Fonts'), {
            target: { value: 'robo' },
        });

        await waitFor(() =>
            expect(api.fetchCatalog).toHaveBeenCalledWith('google', 'robo', 1, expect.any(AbortSignal))
        );
    });

    it('installs a font with the chosen weights', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);
        fireEvent.click(await screen.findByRole('tab', { name: 'Google Fonts' }));
        await screen.findByText('Roboto');

        fireEvent.click(screen.getByRole('button', { name: 'Add' }));
        fireEvent.click(screen.getByRole('button', { name: 'Install' }));

        await waitFor(() =>
            expect(api.installFont).toHaveBeenCalledWith('google', 'roboto', [
                { weight: 400, style: 'normal' },
            ])
        );
        expect(await screen.findByText(/installed successfully/i)).toBeInTheDocument();
    });

    it('bulk-uninstalls selected fonts', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);
        await screen.findByText('Inter');

        fireEvent.click(screen.getByLabelText('Select Inter for uninstall'));
        fireEvent.click(screen.getByRole('button', { name: /Uninstall selected/i }));

        await waitFor(() => expect(api.bulkUninstall).toHaveBeenCalledWith([1]));
    });

    it('keeps the installed list visible when a removal fails', async () => {
        api.uninstallFont.mockRejectedValueOnce(new MockApiError('server exploded', 500));

        render(<FontLibraryModal isOpen onClose={() => {}} />);
        await screen.findByText('Inter');

        fireEvent.click(screen.getByRole('button', { name: 'Uninstall' }));

        expect(await screen.findByText('server exploded')).toBeInTheDocument();
        // The list must not be torn down behind an error state.
        expect(screen.getByText('Inter')).toBeInTheDocument();
    });

    it('renders the upload form on the Custom Upload tab and uploads', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        fireEvent.click(await screen.findByRole('tab', { name: 'Custom Upload' }));

        // The custom-upload tab shows the form, never a catalog browse.
        expect(api.fetchCatalog).not.toHaveBeenCalledWith('custom', expect.anything(), expect.anything());

        fireEvent.change(screen.getByPlaceholderText('e.g. My Brand Sans'), {
            target: { value: 'Brand' },
        });

        const file = new File([new Uint8Array([1])], 'brand.woff2', { type: 'font/woff2' });
        fireEvent.change(screen.getByLabelText('Choose font files'), {
            target: { files: [file] },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Upload font' }));

        await waitFor(() =>
            expect(api.uploadFont).toHaveBeenCalledWith('Brand', [{ file }])
        );
    });

    it('disables upload until a family name and files are chosen', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);
        fireEvent.click(await screen.findByRole('tab', { name: 'Custom Upload' }));

        expect(screen.getByRole('button', { name: 'Upload font' })).toBeDisabled();
    });

    it('gates the UI read-only when the capability is missing', async () => {
        setup({}, true);
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        expect(await screen.findByText(/read-only access/i)).toBeInTheDocument();
        expect(screen.queryByLabelText('Select Inter for uninstall')).toBeNull();
        expect(screen.queryByRole('button', { name: /Uninstall selected/i })).toBeNull();
    });

    it('associates each tab with its panel via ARIA', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        const installedTab = await screen.findByRole('tab', { name: 'Installed' });
        const panel = screen.getByRole('tabpanel');

        expect(installedTab).toHaveAttribute('aria-selected', 'true');
        expect(installedTab).toHaveAttribute('aria-controls', panel.id);
        expect(panel).toHaveAttribute('aria-labelledby', installedTab.id);
    });

    it('keeps a labelled tabpanel mounted for every tab', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);
        // Wait for the sources to load so every provider tab is rendered.
        await screen.findByRole('tab', { name: 'Google Fonts' });

        const tabs = screen.getAllByRole('tab');
        expect(tabs).toHaveLength(3);

        for (const tab of tabs) {
            const controlledId = tab.getAttribute('aria-controls');
            expect(controlledId).toBeTruthy();

            const panel = document.getElementById(controlledId as string);
            expect(panel).not.toBeNull();
            expect(panel).toHaveAttribute('role', 'tabpanel');
            expect(panel).toHaveAttribute('aria-labelledby', tab.id);
        }
    });

    it('leaves vertical arrow keys for scrolling on the horizontal tablist', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        const installedTab = await screen.findByRole('tab', { name: 'Installed' });
        const googleTab = await screen.findByRole('tab', { name: 'Google Fonts' });

        installedTab.focus();
        fireEvent.keyDown(installedTab, { key: 'ArrowDown' });
        fireEvent.keyDown(installedTab, { key: 'ArrowUp' });

        // Neither vertical arrow changes the selection.
        expect(installedTab).toHaveAttribute('aria-selected', 'true');
        expect(googleTab).toHaveAttribute('aria-selected', 'false');
    });

    it('gives only the active tab a tabindex of 0 (roving tabindex)', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        const installedTab = await screen.findByRole('tab', { name: 'Installed' });
        const googleTab = await screen.findByRole('tab', { name: 'Google Fonts' });

        expect(installedTab).toHaveAttribute('tabindex', '0');
        expect(googleTab).toHaveAttribute('tabindex', '-1');
    });

    it('moves between tabs with the arrow keys and activates on focus', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        const installedTab = await screen.findByRole('tab', { name: 'Installed' });
        const googleTab = await screen.findByRole('tab', { name: 'Google Fonts' });

        installedTab.focus();
        fireEvent.keyDown(installedTab, { key: 'ArrowRight' });

        expect(googleTab).toHaveAttribute('aria-selected', 'true');
        expect(googleTab).toHaveFocus();
        // Activating a provider tab kicks off its catalog browse.
        expect(await screen.findByText('Roboto')).toBeInTheDocument();
    });

    it('jumps to the first and last tabs with Home and End', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        const installedTab = await screen.findByRole('tab', { name: 'Installed' });
        const uploadTab = await screen.findByRole('tab', { name: 'Custom Upload' });

        installedTab.focus();
        fireEvent.keyDown(installedTab, { key: 'End' });

        expect(uploadTab).toHaveAttribute('aria-selected', 'true');
        expect(uploadTab).toHaveFocus();

        fireEvent.keyDown(uploadTab, { key: 'Home' });

        expect(installedTab).toHaveAttribute('aria-selected', 'true');
        expect(installedTab).toHaveFocus();
    });

    it('wraps from the last tab to the first with the arrow keys', async () => {
        render(<FontLibraryModal isOpen onClose={() => {}} />);

        const installedTab = await screen.findByRole('tab', { name: 'Installed' });
        const uploadTab = await screen.findByRole('tab', { name: 'Custom Upload' });

        uploadTab.focus();
        fireEvent.keyDown(uploadTab, { key: 'ArrowRight' });

        expect(installedTab).toHaveAttribute('aria-selected', 'true');
        expect(installedTab).toHaveFocus();
    });

    it('flips to read-only when an install is forbidden', async () => {
        api.installFont.mockRejectedValueOnce(new MockApiError('nope', 403, 'forbidden'));

        render(<FontLibraryModal isOpen onClose={() => {}} />);
        fireEvent.click(await screen.findByRole('tab', { name: 'Google Fonts' }));
        await screen.findByText('Roboto');

        fireEvent.click(screen.getByRole('button', { name: 'Add' }));
        fireEvent.click(screen.getByRole('button', { name: 'Install' }));

        expect(await screen.findByText(/read-only access/i)).toBeInTheDocument();
    });
});
