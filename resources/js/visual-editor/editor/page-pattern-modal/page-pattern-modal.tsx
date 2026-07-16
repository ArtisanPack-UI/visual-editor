/**
 * Page-pattern-inserter modal (#639).
 *
 * Renders a WordPress-style "Choose a pattern" dialog for the current
 * post-type context. Combines a pattern grid (grouped by category) with
 * an optional template selector. Selecting a pattern hands its parsed
 * block tree back to the caller; selecting a template writes through
 * the caller-provided setter. `Blank` dismisses cleanly with an empty
 * canvas.
 *
 * The modal is purely presentational — the caller (`editor-app.tsx`)
 * owns the open/close state, the block-insertion side effect, and the
 * template-change side effect. This keeps the modal reusable for
 * both the auto-open flow and the toolbar-triggered manual re-open.
 *
 * @since 1.4.0
 */

import {
    createBlocksFromInnerBlocksTemplate,
    parse,
    type BlockInstance,
} from '@wordpress/blocks';
import { __, sprintf } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    type ChangeEvent,
    type KeyboardEvent as ReactKeyboardEvent,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { PatternRecord } from '../../site-editor/patterns/api-client';

import './page-pattern-modal.css';

/**
 * A template option shown in the selector. `source` lets the modal
 * surface where the template came from ("Theme" for site-editor
 * `Template` records, "CMS" for cms-framework page templates, or any
 * host-defined label) without the caller having to render its own
 * label logic.
 */
export interface TemplateOption {
    /** The slug written to the page's `template` field on selection. */
    readonly slug: string;
    /** Human-readable label rendered in the `<option>`. */
    readonly label: string;
    /** Optional source tag (e.g. "Theme", "CMS"). */
    readonly source?: string;
}

export interface PagePatternModalProps {
    /** Whether the modal is currently rendered. */
    readonly open: boolean;
    /** Fired when the user dismisses via X / Escape / backdrop. */
    readonly onClose: () => void;
    /**
     * All patterns applicable to the current post-type context. The
     * caller pre-fetches and post-type-filters; the modal only groups
     * by category and renders. An empty list renders the empty state
     * (no patterns registered for this post type).
     */
    readonly patterns: readonly PatternRecord[];
    /**
     * Fired when the user picks a pattern. Receives the parsed block
     * tree — never the raw `PatternRecord` — so the caller can
     * `insertBlocks` without another parse pass. `Blank` produces an
     * empty array, which the caller should treat as "leave the canvas
     * alone."
     */
    readonly onInsertBlocks: (blocks: readonly BlockInstance[]) => void;
    /**
     * Template options combining site-editor `Template` records and
     * cms-framework page templates. Empty means "no selector"; the
     * modal renders without the template row entirely.
     */
    readonly templateOptions?: readonly TemplateOption[];
    /** Current template slug, driving the `<select>` value. */
    readonly initialTemplate?: string;
    /**
     * Fired when the user picks a template. Optional — omit when the
     * current post type doesn't support templates.
     */
    readonly onTemplateChange?: (slug: string) => void;
    /** Async loading indicator for the pattern list. */
    readonly loading?: boolean;
    /** Error message from a failed pattern fetch. */
    readonly errorMessage?: string | null;
    /**
     * i18n override for the dialog title. Optional so custom post
     * types can label the modal ("Choose a post pattern",
     * "Choose a landing-page layout", …).
     */
    readonly title?: string;
}

type TemplateNode = [string, Record<string, unknown>?, TemplateNode[]?];

function patternBlocks(pattern: PatternRecord): BlockInstance[] {
    const raw = pattern.content?.raw;

    if (typeof raw === 'string' && raw.trim() !== '') {
        return parse(raw);
    }

    if (Array.isArray(pattern.content?.blocks)) {
        return pattern.content.blocks as BlockInstance[];
    }

    return [];
}

function blocksToTemplate(blocks: readonly BlockInstance[]): TemplateNode[] {
    return blocks.map((block): TemplateNode => {
        const inner = Array.isArray(block.innerBlocks)
            ? (block.innerBlocks as BlockInstance[])
            : [];

        return [
            block.name,
            block.attributes ?? {},
            inner.length > 0 ? blocksToTemplate(inner) : [],
        ];
    });
}

/**
 * Produce a fresh block-tree copy so two inserts of the same pattern
 * don't collide on clientId. Matches the strategy used by
 * `inserter-patterns-panel.tsx`.
 */
function cloneBlocks(blocks: readonly BlockInstance[]): BlockInstance[] {
    if (blocks.length === 0) {
        return [];
    }

    return createBlocksFromInnerBlocksTemplate(blocksToTemplate(blocks));
}

function patternTitle(pattern: PatternRecord): string {
    const raw = pattern.title?.raw?.trim();

    if (raw !== undefined && raw !== '') {
        return raw;
    }

    const rendered = pattern.title?.rendered?.trim();

    if (rendered !== undefined && rendered !== '') {
        return rendered;
    }

    return pattern.slug;
}

function groupByCategory(
    patterns: readonly PatternRecord[]
): ReadonlyArray<readonly [string, readonly PatternRecord[]]> {
    const groups = new Map<string, PatternRecord[]>();

    for (const pattern of patterns) {
        const rawCategories =
            pattern.categories.length > 0 ? pattern.categories : ['uncategorized'];

        // Assign each pattern to exactly one category — the first entry
        // in its `categories` array — so a multi-categorized pattern
        // renders once instead of once per category. Rendering the same
        // pattern in multiple sections would duplicate the visible card
        // and collide on `key` / `data-testid`, and the modal is a
        // pick-one starter picker, not a taxonomy browser.
        const primary = rawCategories[0] ?? 'uncategorized';
        const bucket = groups.get(primary);

        if (bucket === undefined) {
            groups.set(primary, [pattern]);
        } else if (!bucket.some((existing) => existing.id === pattern.id)) {
            bucket.push(pattern);
        }
    }

    return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b));
}

export function PagePatternModal(props: PagePatternModalProps): JSX.Element | null {
    const {
        open,
        onClose,
        patterns,
        onInsertBlocks,
        templateOptions,
        initialTemplate,
        onTemplateChange,
        loading = false,
        errorMessage = null,
        title,
    } = props;

    const titleId = useId();
    const dialogRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        function handleKey(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
            }
        }

        window.addEventListener('keydown', handleKey);

        return () => {
            window.removeEventListener('keydown', handleKey);
        };
    }, [open, onClose]);

    // Move focus into the dialog on open so keyboard users don't have
    // to tab out of the underlying editor to reach the pattern grid.
    useEffect(() => {
        if (!open) {
            return;
        }

        dialogRef.current?.focus();
    }, [open]);

    const grouped = useMemo(() => groupByCategory(patterns), [patterns]);

    const handleBackdropClick = useCallback(() => {
        onClose();
    }, [onClose]);

    const handleDialogClick = useCallback(
        (event: React.MouseEvent<HTMLDivElement>) => {
            // Prevent the backdrop from closing when the click starts
            // inside the dialog.
            event.stopPropagation();
        },
        []
    );

    const handleSelectPattern = useCallback(
        (pattern: PatternRecord): void => {
            const blocks = cloneBlocks(patternBlocks(pattern));

            onInsertBlocks(blocks);
            onClose();
        },
        [onClose, onInsertBlocks]
    );

    const handleTemplateSelect = useCallback(
        (event: ChangeEvent<HTMLSelectElement>): void => {
            onTemplateChange?.(event.target.value);
        },
        [onTemplateChange]
    );

    const handleCardKey = useCallback(
        (event: ReactKeyboardEvent<HTMLButtonElement>, pattern: PatternRecord): void => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            handleSelectPattern(pattern);
        },
        [handleSelectPattern]
    );

    if (!open) {
        return null;
    }

    const dialogTitle = title ?? __('Choose a pattern', TEXT_DOMAIN);
    const shouldRenderTemplateRow =
        templateOptions !== undefined && templateOptions.length > 0 && onTemplateChange !== undefined;

    return (
        <div
            className="ap-page-pattern-modal__backdrop"
            role="presentation"
            data-testid="ap-page-pattern-modal-backdrop"
            onClick={handleBackdropClick}
        >
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                tabIndex={-1}
                className="ap-page-pattern-modal__dialog"
                data-testid="ap-page-pattern-modal"
                onClick={handleDialogClick}
            >
                <header className="ap-page-pattern-modal__header">
                    <h2 id={titleId} className="ap-page-pattern-modal__title">
                        {dialogTitle}
                    </h2>
                    <button
                        type="button"
                        className="ap-page-pattern-modal__close"
                        aria-label={__('Close pattern picker', TEXT_DOMAIN)}
                        data-testid="ap-page-pattern-modal-close"
                        onClick={onClose}
                    >
                        {'×'}
                    </button>
                </header>
                <div className="ap-page-pattern-modal__body">
                    {shouldRenderTemplateRow ? (
                        <div className="ap-page-pattern-modal__template-section">
                            <label
                                className="ap-page-pattern-modal__template-label"
                                htmlFor={`${titleId}-template`}
                            >
                                {__('Template', TEXT_DOMAIN)}
                            </label>
                            <select
                                id={`${titleId}-template`}
                                className="ap-page-pattern-modal__template-select"
                                value={initialTemplate ?? ''}
                                data-testid="ap-page-pattern-modal-template-select"
                                onChange={handleTemplateSelect}
                            >
                                {templateOptions.map((option) => (
                                    <option key={option.slug} value={option.slug}>
                                        {option.source
                                            ? sprintf(
                                                  /* translators: 1: template label. 2: source (Theme, CMS, etc). */
                                                  __('%1$s (%2$s)', TEXT_DOMAIN),
                                                  option.label,
                                                  option.source
                                              )
                                            : option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ) : null}

                    {loading ? (
                        <p
                            className="ap-page-pattern-modal__status"
                            role="status"
                            aria-live="polite"
                            data-testid="ap-page-pattern-modal-loading"
                        >
                            {__('Loading patterns…', TEXT_DOMAIN)}
                        </p>
                    ) : null}

                    {errorMessage !== null && !loading ? (
                        <p
                            className="ap-page-pattern-modal__status ap-page-pattern-modal__error"
                            role="alert"
                            data-testid="ap-page-pattern-modal-error"
                        >
                            {errorMessage}
                        </p>
                    ) : null}

                    {!loading && errorMessage === null && grouped.length === 0 ? (
                        <p
                            className="ap-page-pattern-modal__empty"
                            data-testid="ap-page-pattern-modal-empty"
                        >
                            {__(
                                'No patterns registered for this content type yet.',
                                TEXT_DOMAIN
                            )}
                        </p>
                    ) : null}

                    {!loading && errorMessage === null
                        ? grouped.map(([category, categoryPatterns]) => (
                              <section
                                  key={category}
                                  data-testid={`ap-page-pattern-modal-category-${category}`}
                              >
                                  <h3 className="ap-page-pattern-modal__category-heading">
                                      {category}
                                  </h3>
                                  <div className="ap-page-pattern-modal__grid">
                                      {categoryPatterns.map((pattern) => (
                                          <button
                                              key={pattern.id}
                                              type="button"
                                              className="ap-page-pattern-modal__pattern-card"
                                              data-testid={`ap-page-pattern-modal-pattern-${pattern.slug}`}
                                              onClick={() => handleSelectPattern(pattern)}
                                              onKeyDown={(event) => handleCardKey(event, pattern)}
                                          >
                                              <p className="ap-page-pattern-modal__pattern-title">
                                                  {patternTitle(pattern)}
                                              </p>
                                              <p className="ap-page-pattern-modal__pattern-meta">
                                                  <code>{pattern.slug}</code>
                                              </p>
                                          </button>
                                      ))}
                                  </div>
                              </section>
                          ))
                        : null}
                </div>
            </div>
        </div>
    );
}
