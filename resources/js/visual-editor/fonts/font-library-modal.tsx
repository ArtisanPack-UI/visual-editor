/**
 * Font Library modal (#635).
 *
 * The Site Editor's front door to font management. Tabs across Installed, each
 * registered provider (Google Fonts, Bunny Fonts, and anything a package adds
 * via `ap.visualEditor.registerFontSources`), and Upload. Provider tabs browse
 * and search a paginated catalog with a live preview; installing a font lets
 * the user pick weights/styles, then shows progress and a success confirmation.
 * The Installed tab supports bulk-select uninstall. Every remote provider tab
 * carries a persistent GDPR notice explaining that fonts are self-hosted.
 *
 * When the current user lacks the `manage_fonts` capability the whole modal is
 * read-only: browsing still works, but every mutating control is disabled and
 * an explanation banner is shown. The server enforces the same gate — the UI
 * state only mirrors the `read_only` signal the read endpoints return.
 *
 * Per project memory, this uses inline tab/menu patterns rather than
 * `@artisanpack-ui/react` Popover/Dropdown, which freeze the editor.
 *
 * @since 1.7.0
 */

import { Button, Modal, Notice, SearchControl, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';
import {
    bulkUninstall,
    catalogPreviewUrl,
    fetchCatalog,
    fetchInstalledFonts,
    fetchSources,
    FontLibraryApiError,
    installFont,
    parseVariant,
    uninstallFont,
    uploadFont,
    type CatalogFamily,
    type InstalledFont,
    type UploadFace,
} from './api-client';
import FontPreview from './font-preview';
import {
    emptyCatalog,
    fontLibraryReducer,
    initialState,
    INSTALLED_TAB,
    isInstalled,
    type CatalogState,
    type FontLibraryState,
} from './store';

export interface FontLibraryModalProps {
    /** Whether the modal is rendered. */
    readonly isOpen: boolean;
    /** Fired when the user dismisses the modal. */
    readonly onClose: () => void;
}

const DEFAULT_SAMPLE = 'The quick brown fox jumps over the lazy dog';
const SEARCH_DEBOUNCE_MS = 300;

/**
 * The registry key of the first-party custom-upload provider. Its tab renders
 * the upload form rather than a (permanently empty) catalog browse.
 */
const CUSTOM_UPLOAD_PROVIDER = 'custom';

const STYLES = {
    tabs: {
        display: 'flex',
        flexWrap: 'wrap' as const,
        gap: 4,
        borderBottom: '1px solid #ddd',
        marginBottom: 12,
    },
    tab: (active: boolean): React.CSSProperties => ({
        padding: '8px 14px',
        border: 'none',
        borderBottom: active ? '2px solid #007cba' : '2px solid transparent',
        background: 'transparent',
        color: active ? '#007cba' : '#1e1e1e',
        fontWeight: active ? 600 : 400,
        cursor: 'pointer',
    }),
    body: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: 12,
        minHeight: 360,
        maxHeight: '62vh',
    },
    list: {
        flex: '1 1 auto',
        overflowY: 'auto' as const,
        display: 'flex',
        flexDirection: 'column' as const,
        gap: 8,
    },
    row: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: 8,
        padding: 12,
        border: '1px solid #e0e0e0',
        borderRadius: 6,
        background: '#fff',
    },
    rowHeader: {
        display: 'flex',
        alignItems: 'center' as const,
        justifyContent: 'space-between' as const,
        gap: 12,
    },
    variantGrid: {
        display: 'flex',
        flexWrap: 'wrap' as const,
        gap: 8,
        marginTop: 4,
    },
    variantLabel: {
        display: 'inline-flex',
        alignItems: 'center' as const,
        gap: 4,
        fontSize: 13,
    },
    actions: {
        display: 'flex',
        justifyContent: 'flex-end' as const,
        gap: 8,
        marginTop: 12,
        paddingTop: 12,
        borderTop: '1px solid #ddd',
    },
};

function variantLabel(token: string): string {
    const { weight, style } = parseVariant(token);

    return style === 'italic'
        ? sprintf(
              /* translators: %d: font weight number (e.g. 400). */
              __('%d italic', TEXT_DOMAIN),
              weight
          )
        : String(weight);
}

/** The Installed tab: bulk-select uninstall of installed fonts. */
function InstalledTab({
    state,
    sampleText,
    onToggle,
    onSelectAll,
    onClearSelection,
    onUninstall,
    onBulkUninstall,
}: {
    state: FontLibraryState;
    sampleText: string;
    onToggle: (id: number) => void;
    onSelectAll: () => void;
    onClearSelection: () => void;
    onUninstall: (font: InstalledFont) => void;
    onBulkUninstall: () => void;
}) {
    const { installed, installedStatus, installedError, selectedForRemoval, readOnly } = state;

    if (installedStatus === 'loading') {
        return <Spinner />;
    }

    if (installedStatus === 'error') {
        return (
            <Notice status="error" isDismissible={false}>
                {installedError}
            </Notice>
        );
    }

    if (installed.length === 0) {
        return (
            <Notice status="info" isDismissible={false}>
                {__('No fonts installed yet. Browse a provider tab to add one.', TEXT_DOMAIN)}
            </Notice>
        );
    }

    const allSelected = selectedForRemoval.length === installed.length;

    return (
        <>
            {!readOnly && (
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    <Button
                        variant="tertiary"
                        onClick={allSelected ? onClearSelection : onSelectAll}
                    >
                        {allSelected
                            ? __('Clear selection', TEXT_DOMAIN)
                            : __('Select all', TEXT_DOMAIN)}
                    </Button>
                    <Button
                        variant="secondary"
                        isDestructive
                        disabled={selectedForRemoval.length === 0}
                        onClick={onBulkUninstall}
                    >
                        {sprintf(
                            /* translators: %d: number of selected fonts. */
                            __('Uninstall selected (%d)', TEXT_DOMAIN),
                            selectedForRemoval.length
                        )}
                    </Button>
                </div>
            )}
            <div style={STYLES.list}>
                {installed.map((font) => (
                    <div key={font.id} style={STYLES.row}>
                        <div style={STYLES.rowHeader}>
                            <label style={STYLES.variantLabel}>
                                {!readOnly && (
                                    <input
                                        type="checkbox"
                                        checked={selectedForRemoval.includes(font.id)}
                                        onChange={() => onToggle(font.id)}
                                        aria-label={sprintf(
                                            /* translators: %s: font family name. */
                                            __('Select %s for uninstall', TEXT_DOMAIN),
                                            font.family
                                        )}
                                    />
                                )}
                                <strong>{font.family}</strong>
                                <span style={{ color: '#757575', fontSize: 12 }}>
                                    {sprintf(
                                        /* translators: %d: number of font faces. */
                                        __('%d faces', TEXT_DOMAIN),
                                        font.faces.length
                                    )}
                                </span>
                            </label>
                            {!readOnly && (
                                <Button
                                    variant="tertiary"
                                    isDestructive
                                    onClick={() => onUninstall(font)}
                                >
                                    {__('Uninstall', TEXT_DOMAIN)}
                                </Button>
                            )}
                        </div>
                        <FontPreview
                            family={font.family}
                            faces={font.faces}
                            sampleText={sampleText}
                        />
                    </div>
                ))}
            </div>
        </>
    );
}

/** A single catalog row with an expandable weight/style picker. */
function CatalogRow({
    family,
    previewUrl,
    alreadyInstalled,
    readOnly,
    installing,
    sampleText,
    onInstall,
}: {
    family: CatalogFamily;
    previewUrl?: string;
    alreadyInstalled: boolean;
    readOnly: boolean;
    installing: boolean;
    sampleText: string;
    onInstall: (family: CatalogFamily, variants: string[]) => void;
}) {
    const [expanded, setExpanded] = useState(false);
    const [selected, setSelected] = useState<string[]>(() =>
        family.variants.includes('400') ? ['400'] : family.variants.slice(0, 1)
    );

    const toggleVariant = (token: string): void => {
        setSelected((current) =>
            current.includes(token)
                ? current.filter((value) => value !== token)
                : [...current, token]
        );
    };

    return (
        <div style={STYLES.row}>
            <div style={STYLES.rowHeader}>
                <strong>{family.family}</strong>
                {alreadyInstalled ? (
                    <span style={{ color: '#008a20', fontSize: 13 }}>
                        {__('Installed', TEXT_DOMAIN)}
                    </span>
                ) : (
                    !readOnly && (
                        <Button
                            variant="secondary"
                            onClick={() => setExpanded((value) => !value)}
                            aria-expanded={expanded}
                        >
                            {__('Add', TEXT_DOMAIN)}
                        </Button>
                    )
                )}
            </div>
            <FontPreview family={family.family} previewUrl={previewUrl} sampleText={sampleText} />
            {expanded && !alreadyInstalled && !readOnly && (
                <div>
                    <div style={STYLES.variantGrid} role="group" aria-label={__('Weights and styles', TEXT_DOMAIN)}>
                        {family.variants.map((token) => (
                            <label key={token} style={STYLES.variantLabel}>
                                <input
                                    type="checkbox"
                                    checked={selected.includes(token)}
                                    onChange={() => toggleVariant(token)}
                                />
                                {variantLabel(token)}
                            </label>
                        ))}
                    </div>
                    <div style={{ marginTop: 8, display: 'flex', gap: 8, alignItems: 'center' }}>
                        <Button
                            variant="primary"
                            disabled={selected.length === 0 || installing}
                            onClick={() => onInstall(family, selected)}
                        >
                            {installing
                                ? __('Installing…', TEXT_DOMAIN)
                                : __('Install', TEXT_DOMAIN)}
                        </Button>
                        {installing && <Spinner />}
                    </div>
                </div>
            )}
        </div>
    );
}

/** A provider catalog tab: search + paginated browse + install. */
function CatalogTab({
    provider,
    label,
    selfHostable,
    catalog,
    state,
    sampleText,
    onSearch,
    onLoadMore,
    onInstall,
}: {
    provider: string;
    label: string;
    selfHostable: boolean;
    catalog: CatalogState;
    state: FontLibraryState;
    sampleText: string;
    onSearch: (query: string) => void;
    onLoadMore: () => void;
    onInstall: (family: CatalogFamily, variants: string[]) => void;
}) {
    const installFamilySlug = state.install.status === 'installing' ? state.install.slug : null;

    return (
        <>
            <Notice status="info" isDismissible={false}>
                {selfHostable
                    ? sprintf(
                          /* translators: %s: provider label (e.g. Google Fonts). */
                          __(
                              'Fonts installed from %s are downloaded once to this site and served locally — your visitors never hit the provider’s CDN, keeping the site GDPR-compliant.',
                              TEXT_DOMAIN
                          ),
                          label
                      )
                    : sprintf(
                          /* translators: %s: provider label. */
                          __(
                              '%s does not support self-hosting, so its fonts cannot be installed in this version.',
                              TEXT_DOMAIN
                          ),
                          label
                      )}
            </Notice>
            <SearchControl
                value={catalog.query}
                onChange={onSearch}
                label={sprintf(
                    /* translators: %s: provider label. */
                    __('Search %s', TEXT_DOMAIN),
                    label
                )}
            />
            {catalog.status === 'error' && (
                <Notice status="error" isDismissible={false}>
                    {catalog.error}
                </Notice>
            )}
            <div style={STYLES.list}>
                {catalog.families.map((family) => (
                    <CatalogRow
                        key={family.slug}
                        family={family}
                        previewUrl={catalogPreviewUrl(provider, family)}
                        alreadyInstalled={isInstalled(state, provider, family.slug)}
                        readOnly={state.readOnly || !selfHostable}
                        installing={installFamilySlug === family.slug}
                        sampleText={sampleText}
                        onInstall={onInstall}
                    />
                ))}
                {catalog.status === 'loading' && <Spinner />}
                {catalog.status === 'ready' && catalog.families.length === 0 && (
                    <Notice status="info" isDismissible={false}>
                        {__('No fonts matched your search.', TEXT_DOMAIN)}
                    </Notice>
                )}
            </div>
            {catalog.hasMore && catalog.status !== 'loading' && (
                <div style={{ display: 'flex', justifyContent: 'center' }}>
                    <Button variant="secondary" onClick={onLoadMore}>
                        {__('Load more', TEXT_DOMAIN)}
                    </Button>
                </div>
            )}
        </>
    );
}

const UPLOAD_STYLES = {
    field: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: 6,
        fontWeight: 500 as const,
    },
    input: {
        padding: '8px 10px',
        border: '1px solid #949494',
        borderRadius: 4,
        fontSize: 14,
        fontWeight: 400 as const,
        background: '#fff',
        color: '#1e1e1e',
    },
    fileList: {
        margin: 0,
        padding: '8px 12px',
        listStyle: 'none' as const,
        border: '1px solid #e0e0e0',
        borderRadius: 4,
        background: '#f6f7f7',
        fontSize: 13,
    },
};

/** The Custom Upload tab: self-host a user's own font files. */
function UploadTab({
    readOnly,
    uploading,
    error,
    onUpload,
}: {
    readOnly: boolean;
    uploading: boolean;
    error: string | null;
    onUpload: (family: string, files: File[]) => void;
}) {
    const [family, setFamily] = useState('');
    const [files, setFiles] = useState<File[]>([]);

    if (readOnly) {
        return (
            <Notice status="warning" isDismissible={false}>
                {__('You do not have permission to upload fonts.', TEXT_DOMAIN)}
            </Notice>
        );
    }

    const canUpload = family.trim() !== '' && files.length > 0 && !uploading;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 520 }}>
            <label style={UPLOAD_STYLES.field}>
                {__('Font family name', TEXT_DOMAIN)}
                <input
                    type="text"
                    style={UPLOAD_STYLES.input}
                    value={family}
                    onChange={(event) => setFamily(event.target.value)}
                    placeholder={__('e.g. My Brand Sans', TEXT_DOMAIN)}
                />
            </label>
            <label style={UPLOAD_STYLES.field}>
                {__('Font files (.woff2, .woff, .ttf, .otf)', TEXT_DOMAIN)}
                <input
                    type="file"
                    multiple
                    accept=".woff2,.woff,.ttf,.otf"
                    aria-label={__('Choose font files', TEXT_DOMAIN)}
                    style={{ ...UPLOAD_STYLES.input, padding: '6px 8px' }}
                    onChange={(event) =>
                        setFiles(event.target.files ? Array.from(event.target.files) : [])
                    }
                />
            </label>
            {files.length > 0 && (
                <ul style={UPLOAD_STYLES.fileList}>
                    {files.map((file) => (
                        <li key={file.name}>{file.name}</li>
                    ))}
                </ul>
            )}
            {error && (
                <Notice status="error" isDismissible={false}>
                    {error}
                </Notice>
            )}
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                <Button
                    variant="primary"
                    disabled={!canUpload}
                    onClick={() => onUpload(family.trim(), files)}
                >
                    {uploading ? __('Uploading…', TEXT_DOMAIN) : __('Upload font', TEXT_DOMAIN)}
                </Button>
                {uploading && <Spinner />}
            </div>
        </div>
    );
}

export default function FontLibraryModal({ isOpen, onClose }: FontLibraryModalProps) {
    const [state, dispatch] = useReducer(fontLibraryReducer, initialState);
    const [sampleText, setSampleText] = useState<string>(__(DEFAULT_SAMPLE, TEXT_DOMAIN));
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Load the installed list and provider list when the modal opens.
    useEffect(() => {
        if (!isOpen) {
            return;
        }

        let cancelled = false;

        dispatch({ type: 'INSTALLED_LOADING' });
        fetchInstalledFonts()
            .then((result) => {
                if (cancelled) return;
                dispatch({
                    type: 'INSTALLED_LOADED',
                    fonts: result.fonts,
                    readOnly: result.readOnly,
                });
            })
            .catch((error: FontLibraryApiError) => {
                if (!cancelled) dispatch({ type: 'INSTALLED_ERROR', message: error.message });
            });

        dispatch({ type: 'SOURCES_LOADING' });
        fetchSources()
            .then((result) => {
                if (cancelled) return;
                dispatch({
                    type: 'SOURCES_LOADED',
                    sources: result.sources,
                    readOnly: result.readOnly,
                });
            })
            .catch((error: FontLibraryApiError) => {
                if (!cancelled) dispatch({ type: 'SOURCES_ERROR', message: error.message });
            });

        return () => {
            cancelled = true;
        };
    }, [isOpen]);

    const loadCatalog = useCallback((provider: string, query: string, page: number) => {
        dispatch({ type: 'CATALOG_REQUEST', provider, query, page });
        fetchCatalog(provider, query, page)
            .then((result) => {
                dispatch({
                    type: 'CATALOG_SUCCESS',
                    provider,
                    page: result.page,
                    families: result.families,
                    hasMore: result.has_more,
                });
            })
            .catch((error: FontLibraryApiError) => {
                dispatch({ type: 'CATALOG_FAILURE', provider, message: error.message });
            });
    }, []);

    const isUploadTab = state.activeTab === CUSTOM_UPLOAD_PROVIDER;

    // A browsable provider tab: a registered source that isn't the synthetic
    // custom-upload source (which has no catalog — it renders the upload form).
    const isProviderTab = useMemo(
        () =>
            !isUploadTab &&
            state.sources.some((source) => source.key === state.activeTab),
        [isUploadTab, state.sources, state.activeTab]
    );

    // First browse when a provider tab is opened with no results yet.
    useEffect(() => {
        if (!isProviderTab) {
            return;
        }

        const catalog = state.catalogs[state.activeTab];

        if (!catalog || catalog.status === 'idle') {
            loadCatalog(state.activeTab, '', 1);
        }
    }, [isProviderTab, state.activeTab, state.catalogs, loadCatalog]);

    const handleSearch = useCallback(
        (provider: string, query: string) => {
            // Reflect the field immediately, debounce the fetch.
            dispatch({ type: 'CATALOG_REQUEST', provider, query, page: 1 });

            if (searchTimer.current) {
                clearTimeout(searchTimer.current);
            }

            searchTimer.current = setTimeout(() => {
                loadCatalog(provider, query, 1);
            }, SEARCH_DEBOUNCE_MS);
        },
        [loadCatalog]
    );

    useEffect(
        () => () => {
            if (searchTimer.current) {
                clearTimeout(searchTimer.current);
            }
        },
        []
    );

    const handleInstall = useCallback(
        (provider: string, family: CatalogFamily, variants: string[]) => {
            const faces = variants.map(parseVariant);

            dispatch({ type: 'INSTALL_START', slug: family.slug, family: family.family });
            installFont(provider, family.slug, faces)
                .then((font) => {
                    dispatch({ type: 'INSTALL_SUCCESS', font });
                })
                .catch((error: FontLibraryApiError) => {
                    dispatch({ type: 'INSTALL_ERROR', message: error.message });

                    if (error.code === 'forbidden') {
                        dispatch({ type: 'SET_READ_ONLY', readOnly: true });
                    }
                });
        },
        []
    );

    const handleUninstall = useCallback((font: InstalledFont) => {
        uninstallFont(font.id)
            .then(() => {
                dispatch({ type: 'FONTS_REMOVED', ids: [font.id] });
            })
            .catch((error: FontLibraryApiError) => {
                dispatch({ type: 'INSTALLED_ERROR', message: error.message });
            });
    }, []);

    const handleBulkUninstall = useCallback(() => {
        const ids = state.selectedForRemoval;

        if (ids.length === 0) {
            return;
        }

        bulkUninstall(ids)
            .then(() => {
                dispatch({ type: 'FONTS_REMOVED', ids });
            })
            .catch((error: FontLibraryApiError) => {
                dispatch({ type: 'INSTALLED_ERROR', message: error.message });
            });
    }, [state.selectedForRemoval]);

    const handleUpload = useCallback((family: string, files: File[]) => {
        const faces: UploadFace[] = files.map((file) => ({ file }));

        setUploading(true);
        setUploadError(null);
        uploadFont(family, faces)
            .then((font) => {
                dispatch({ type: 'INSTALL_SUCCESS', font });
                dispatch({ type: 'SET_ACTIVE_TAB', tab: INSTALLED_TAB });
            })
            .catch((error: FontLibraryApiError) => {
                setUploadError(error.message);

                if (error.code === 'forbidden') {
                    dispatch({ type: 'SET_READ_ONLY', readOnly: true });
                }
            })
            .finally(() => {
                setUploading(false);
            });
    }, []);

    if (!isOpen) {
        return null;
    }

    const activeCatalog = state.catalogs[state.activeTab];
    const activeSource = state.sources.find((source) => source.key === state.activeTab);

    return (
        <Modal
            title={__('Font Library', TEXT_DOMAIN)}
            onRequestClose={onClose}
            size="large"
        >
            {state.readOnly && (
                <Notice status="warning" isDismissible={false}>
                    {__(
                        'You have read-only access to the Font Library. Browsing is available, but installing, uploading, and removing fonts require the “manage fonts” capability.',
                        TEXT_DOMAIN
                    )}
                </Notice>
            )}

            {state.install.status === 'success' && (
                <Notice
                    status="success"
                    onRemove={() => dispatch({ type: 'INSTALL_RESET' })}
                >
                    {sprintf(
                        /* translators: %s: font family name. */
                        __('“%s” installed successfully.', TEXT_DOMAIN),
                        state.install.family ?? ''
                    )}
                </Notice>
            )}

            {state.install.status === 'error' && (
                <Notice status="error" onRemove={() => dispatch({ type: 'INSTALL_RESET' })}>
                    {state.install.error}
                </Notice>
            )}

            <div style={STYLES.tabs} role="tablist" aria-label={__('Font sources', TEXT_DOMAIN)}>
                <button
                    type="button"
                    role="tab"
                    aria-selected={state.activeTab === INSTALLED_TAB}
                    style={STYLES.tab(state.activeTab === INSTALLED_TAB)}
                    onClick={() => dispatch({ type: 'SET_ACTIVE_TAB', tab: INSTALLED_TAB })}
                >
                    {__('Installed', TEXT_DOMAIN)}
                </button>
                {state.sources.map((source) => (
                    <button
                        key={source.key}
                        type="button"
                        role="tab"
                        aria-selected={state.activeTab === source.key}
                        style={STYLES.tab(state.activeTab === source.key)}
                        onClick={() => dispatch({ type: 'SET_ACTIVE_TAB', tab: source.key })}
                    >
                        {source.label}
                    </button>
                ))}
            </div>

            <div style={{ marginBottom: 12 }}>
                <FontPreview
                    family="inherit"
                    sampleText={sampleText}
                    onSampleTextChange={setSampleText}
                    fontSize={16}
                />
            </div>

            <div style={STYLES.body}>
                {state.activeTab === INSTALLED_TAB && (
                    <InstalledTab
                        state={state}
                        sampleText={sampleText}
                        onToggle={(id) => dispatch({ type: 'TOGGLE_SELECT', id })}
                        onSelectAll={() =>
                            dispatch({
                                type: 'SELECT_ALL',
                                ids: state.installed.map((font) => font.id),
                            })
                        }
                        onClearSelection={() => dispatch({ type: 'CLEAR_SELECTION' })}
                        onUninstall={handleUninstall}
                        onBulkUninstall={handleBulkUninstall}
                    />
                )}

                {isProviderTab && activeSource && (
                    <CatalogTab
                        provider={activeSource.key}
                        label={activeSource.label}
                        selfHostable={activeSource.is_self_hostable}
                        catalog={activeCatalog ?? emptyCatalog}
                        state={state}
                        sampleText={sampleText}
                        onSearch={(query) => handleSearch(activeSource.key, query)}
                        onLoadMore={() =>
                            loadCatalog(
                                activeSource.key,
                                activeCatalog?.query ?? '',
                                (activeCatalog?.page ?? 1) + 1
                            )
                        }
                        onInstall={(family, variants) =>
                            handleInstall(activeSource.key, family, variants)
                        }
                    />
                )}

                {isUploadTab && (
                    <UploadTab
                        readOnly={state.readOnly}
                        uploading={uploading}
                        error={uploadError}
                        onUpload={handleUpload}
                    />
                )}
            </div>

            <div style={STYLES.actions}>
                <Button variant="tertiary" onClick={onClose}>
                    {__('Close', TEXT_DOMAIN)}
                </Button>
            </div>
        </Modal>
    );
}
