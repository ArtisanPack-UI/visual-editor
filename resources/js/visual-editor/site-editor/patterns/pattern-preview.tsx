/**
 * Rendered pattern preview for pattern cards (#832).
 *
 * Shows a scaled-down, non-interactive render of a pattern as it looks on
 * the front end. The server renders the pattern with the Blade renderer
 * (`POST {apiBase}/patterns/preview`); this component drops that HTML into
 * a sandboxed `srcdoc` iframe laid out at the pattern's viewport width
 * (default 1200px) and CSS-scales it down to the card's width, with the
 * height capped and the overflow cropped.
 *
 * - Lazy: nothing is fetched until the card scrolls near the viewport, and
 *   the cards that come into view together share one batched request
 *   (`pattern-preview-loader.ts`).
 * - Static: the iframe is sandboxed without `allow-scripts`, so block
 *   JavaScript (carousels, animations) never runs. `allow-same-origin` only
 *   lets the editor measure the rendered height and lets the preview load
 *   the site's fonts; with scripts disabled the frame can't act on it.
 *   No `blob:` URLs, so the editor's CSP stays clean.
 * - Inert: the preview is `aria-hidden`, `inert` and out of the tab order,
 *   so the card's own button stays the only focus target.
 * - Fallback: the block-name tree (`PatternThumbnail`) shows while loading,
 *   when no API base is available, and when rendering fails.
 */

import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type CSSProperties,
} from 'react';

import type { PatternRecord } from './api-client';
import {
    getPatternPreviewLoader,
    patternContentSignature,
    type PatternPreviewResult,
} from './pattern-preview-loader';
import { PatternThumbnail } from './pattern-thumbnail';

import './pattern-preview.css';

/** Layout width when a pattern declares none (WordPress's default). */
export const DEFAULT_PATTERN_VIEWPORT_WIDTH = 1200;

/** Narrowest / widest viewport width a pattern may declare. */
export const MIN_PATTERN_VIEWPORT_WIDTH = 320;
export const MAX_PATTERN_VIEWPORT_WIDTH = 2560;

/** Preview height cap, as a ratio of the card's width. */
export const PATTERN_PREVIEW_MAX_HEIGHT_RATIO = 0.75;

/** How far outside the viewport a card starts loading. */
const IN_VIEW_ROOT_MARGIN = '200px';

/**
 * Styles appended after the site's own: keep the document static and
 * stop it from showing scrollbars inside the cropped frame.
 */
const PREVIEW_DOCUMENT_STYLES =
    'html,body{margin:0;overflow:hidden;pointer-events:none;}' +
    '*{animation:none!important;transition:none!important;}';

export interface PatternPreviewProps {
    pattern: PatternRecord;
    /** Pattern title, used by the block-name fallback. */
    title: string;
    /**
     * Base URL of the visual-editor API. Without one the card shows the
     * block-name tree only.
     */
    apiBase?: string | null;
}

/**
 * The width a pattern previews at: its declared `viewport_width` clamped
 * to the supported range, or the 1200px default when missing or invalid.
 */
export function patternViewportWidth(pattern: PatternRecord): number {
    const declared = pattern.viewport_width;

    if (
        typeof declared !== 'number' ||
        !Number.isFinite(declared) ||
        declared <= 0
    ) {
        return DEFAULT_PATTERN_VIEWPORT_WIDTH;
    }

    return Math.min(
        MAX_PATTERN_VIEWPORT_WIDTH,
        Math.max(MIN_PATTERN_VIEWPORT_WIDTH, Math.round(declared))
    );
}

function isEmptyPattern(pattern: PatternRecord): boolean {
    const raw = pattern.content?.raw;
    const blocks = pattern.content?.blocks;

    return (
        (typeof raw !== 'string' || raw.trim() === '') &&
        (!Array.isArray(blocks) || blocks.length === 0)
    );
}

/**
 * Flips to `true` the first time the element comes within
 * {@link IN_VIEW_ROOT_MARGIN} of the viewport, then stops observing.
 * Stays `false` where `IntersectionObserver` is unavailable, so those
 * environments keep the fallback and send no requests.
 */
function useHasBeenInView(
    ref: React.RefObject<Element>,
    enabled: boolean
): boolean {
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (
            !enabled ||
            inView ||
            element === null ||
            typeof IntersectionObserver === 'undefined'
        ) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setInView(true);
                    observer.disconnect();
                }
            },
            { rootMargin: IN_VIEW_ROOT_MARGIN }
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [enabled, inView, ref]);

    return inView;
}

function usePatternPreviewResult(
    apiBase: string | null | undefined,
    pattern: PatternRecord,
    enabled: boolean
): PatternPreviewResult | null {
    const [result, setResult] = useState<PatternPreviewResult | null>(null);

    const id = String(pattern.id);
    const signature = useMemo(
        () => patternContentSignature(pattern.content?.raw, pattern.content?.blocks),
        [pattern.content?.raw, pattern.content?.blocks]
    );

    useEffect(() => {
        if (!enabled || typeof apiBase !== 'string' || apiBase === '') {
            return undefined;
        }

        let cancelled = false;

        void getPatternPreviewLoader(apiBase)
            .load(id, signature)
            .then((loaded) => {
                if (!cancelled) {
                    setResult(loaded);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [apiBase, enabled, id, signature]);

    return result;
}

function buildPreviewDocument(result: PatternPreviewResult): string {
    return (
        '<!DOCTYPE html><html><head><meta charset="utf-8">' +
        result.styles +
        `<style>${PREVIEW_DOCUMENT_STYLES}</style>` +
        '</head><body>' +
        result.html +
        '</body></html>'
    );
}

interface PreviewFrameProps {
    result: PatternPreviewResult;
    viewportWidth: number;
}

function PreviewFrame(props: PreviewFrameProps): JSX.Element {
    const { result, viewportWidth } = props;

    const containerRef = useRef<HTMLDivElement | null>(null);
    const [width, setWidth] = useState(0);
    const [contentHeight, setContentHeight] = useState<number | null>(null);

    useEffect(() => {
        const element = containerRef.current;

        if (element === null) {
            return undefined;
        }

        setWidth(element.clientWidth);

        if (typeof ResizeObserver === 'undefined') {
            return undefined;
        }

        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];

            if (entry !== undefined) {
                setWidth(entry.contentRect.width);
            }
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const srcDoc = useMemo(() => buildPreviewDocument(result), [result]);

    const scale = width > 0 ? width / viewportWidth : 0;
    const maxHeight = width * PATTERN_PREVIEW_MAX_HEIGHT_RATIO;
    const frameHeight =
        contentHeight === null
            ? maxHeight
            : Math.min(contentHeight * scale, maxHeight);

    const handleLoad = (event: React.SyntheticEvent<HTMLIFrameElement>): void => {
        const body = event.currentTarget.contentDocument?.body;

        if (body !== null && body !== undefined && body.scrollHeight > 0) {
            setContentHeight(body.scrollHeight);
        }
    };

    const frameStyle: CSSProperties = {
        width: `${viewportWidth}px`,
        height: scale > 0 ? `${frameHeight / scale}px` : '0px',
        transform: `scale(${scale})`,
    };

    return (
        <div
            ref={containerRef}
            className="ap-pattern-preview"
            data-testid="ap-pattern-preview"
            data-viewport-width={viewportWidth}
            style={{ height: `${frameHeight}px` }}
        >
            <iframe
                className="ap-pattern-preview__frame"
                title=""
                srcDoc={srcDoc}
                sandbox="allow-same-origin"
                tabIndex={-1}
                loading="lazy"
                style={frameStyle}
                onLoad={handleLoad}
            />
        </div>
    );
}

export function PatternPreview(props: PatternPreviewProps): JSX.Element {
    const { pattern, title, apiBase } = props;

    const wrapperRef = useRef<HTMLDivElement | null>(null);
    const empty = isEmptyPattern(pattern);
    const canLoad = !empty && typeof apiBase === 'string' && apiBase !== '';
    const inView = useHasBeenInView(wrapperRef, canLoad);
    const result = usePatternPreviewResult(apiBase, pattern, canLoad && inView);

    return (
        <div
            ref={wrapperRef}
            className="ap-pattern-preview-slot"
            data-testid="ap-pattern-preview-slot"
            data-preview-state={result === null ? 'fallback' : 'rendered'}
            aria-hidden="true"
            // `inert` keeps the iframe and anything inside it out of focus
            // and the accessibility tree; React 18 has no typed prop for it.
            {...{ inert: '' }}
        >
            {result === null ? (
                <PatternThumbnail
                    blocks={pattern.content?.blocks ?? []}
                    rawContent={pattern.content?.raw}
                    title={title}
                />
            ) : (
                <PreviewFrame
                    result={result}
                    viewportWidth={patternViewportWidth(pattern)}
                />
            )}
        </div>
    );
}
