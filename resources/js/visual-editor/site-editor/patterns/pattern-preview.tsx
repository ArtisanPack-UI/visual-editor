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
 * - Lazy: nothing is fetched until the card scrolls near its scroll
 *   container (the surface passes it through
 *   {@link PatternPreviewScrollRootContext}), and the cards that come into
 *   view together share one batched request (`pattern-preview-loader.ts`).
 * - Paced: resolved cards mount their iframes through a shared frame
 *   queue (`pattern-preview-mount-queue.ts`), a few per frame, and a card
 *   scrolled far out of view unmounts its iframe — keeping its measured
 *   height so the list doesn't jump — and remounts it on the way back.
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
    createContext,
    useCallback,
    useContext,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    version as reactVersion,
    type CSSProperties,
    type RefObject,
} from 'react';

import type { PatternRecord } from './api-client';
import {
    getPatternPreviewLoader,
    patternContentSignature,
    type PatternPreviewResult,
} from './pattern-preview-loader';
import { schedulePreviewMount } from './pattern-preview-mount-queue';
import { PatternThumbnail } from './pattern-thumbnail';

import './pattern-preview.css';

/** Layout width when a pattern declares none (WordPress's default). */
export const DEFAULT_PATTERN_VIEWPORT_WIDTH = 1200;

/** Narrowest / widest viewport width a pattern may declare. */
export const MIN_PATTERN_VIEWPORT_WIDTH = 320;
export const MAX_PATTERN_VIEWPORT_WIDTH = 2560;

/** Preview height cap, as a ratio of the card's width. */
export const PATTERN_PREVIEW_MAX_HEIGHT_RATIO = 0.75;

/** How far outside its scroll container a card starts loading. */
export const PATTERN_PREVIEW_IN_VIEW_ROOT_MARGIN = '200px';

/**
 * How far outside its scroll container a rendered card keeps its iframe
 * mounted. Further out, the iframe is unmounted until the card scrolls
 * back within range.
 *
 * @since 1.13.0
 */
export const PATTERN_PREVIEW_KEEP_MOUNTED_ROOT_MARGIN = '1500px';

/**
 * The scroll container pattern previews sit in, as a ref. Surfaces with
 * their own scrolling panel (the inserter, the page-pattern modal, the
 * patterns grid) provide it so the lazy-load `rootMargin` prefetch
 * measures against that panel; the viewport would clip it.
 *
 * @since 1.13.0
 */
export const PatternPreviewScrollRootContext = createContext<RefObject<Element> | null>(null);

/**
 * Props that make an element `inert`, for the running React version.
 * React 18 doesn't know the attribute, so it needs the string form to
 * render it; React 19 treats `inert` as a boolean prop and drops `''`.
 *
 * @since 1.13.0
 *
 * @param version React version string; defaults to the running one.
 */
export function inertProps(version: string = reactVersion): Record<string, unknown> {
    const major = Number.parseInt(version, 10);

    return Number.isFinite(major) && major >= 19 ? { inert: true } : { inert: '' };
}

const INERT_PROPS = inertProps();

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
    /**
     * Scroll container the card sits in, for the lazy-load observers.
     * Overrides {@link PatternPreviewScrollRootContext}; omit both to
     * observe against the viewport.
     *
     * @since 1.13.0
     */
    scrollRoot?: Element | null;
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

/** The observer root: the explicit prop, else the context's element. */
function useScrollRoot(scrollRoot: Element | null | undefined): () => Element | null {
    const contextRoot = useContext(PatternPreviewScrollRootContext);

    return () => scrollRoot ?? contextRoot?.current ?? null;
}

/**
 * Flips to `true` the first time the element comes within
 * {@link PATTERN_PREVIEW_IN_VIEW_ROOT_MARGIN} of its scroll root, then
 * stops observing. Stays `false` where `IntersectionObserver` is
 * unavailable, so those environments keep the fallback and send no
 * requests.
 */
function useHasBeenInView(
    ref: React.RefObject<Element>,
    enabled: boolean,
    getRoot: () => Element | null
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
            { root: getRoot(), rootMargin: PATTERN_PREVIEW_IN_VIEW_ROOT_MARGIN }
        );

        observer.observe(element);

        return () => observer.disconnect();
        // `getRoot` reads a prop or ref whose element doesn't change
        // while the card is mounted, so it isn't a dependency.
    }, [enabled, inView, ref]);

    return inView;
}

/**
 * Tracks whether the element is within
 * {@link PATTERN_PREVIEW_KEEP_MOUNTED_ROOT_MARGIN} of its scroll root.
 * Starts `true` (a card only renders once it has been in view) and keeps
 * observing while enabled.
 */
function useIsNearScrollRoot(
    ref: React.RefObject<Element>,
    enabled: boolean,
    getRoot: () => Element | null
): boolean {
    const [near, setNear] = useState(true);

    useEffect(() => {
        const element = ref.current;

        if (!enabled || element === null || typeof IntersectionObserver === 'undefined') {
            setNear(true);

            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const latest = entries[entries.length - 1];

                if (latest !== undefined) {
                    setNear(latest.isIntersecting);
                }
            },
            { root: getRoot(), rootMargin: PATTERN_PREVIEW_KEEP_MOUNTED_ROOT_MARGIN }
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [enabled, ref]);

    return near;
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
    srcDoc: string;
    viewportWidth: number;
    /**
     * The document's measured height at layout width, or `null` until it
     * has loaded. Held by the card so a remounted frame keeps it.
     */
    contentHeight: number | null;
    /** Reports a new measurement of `srcDoc`. */
    onMeasure: (srcDoc: string, height: number) => void;
    /** Reports the frame's rendered (scaled) height whenever it changes. */
    onHeightChange?: (height: number) => void;
}

/**
 * The rendered document's height. `documentElement` catches a first
 * child's top margin collapsing through `body`, which `body.scrollHeight`
 * misses.
 *
 * @since 1.13.0
 */
export function measurePreviewDocument(doc: Document): number {
    return Math.max(
        doc.documentElement?.scrollHeight ?? 0,
        doc.body?.scrollHeight ?? 0
    );
}

function PreviewFrame(props: PreviewFrameProps): JSX.Element {
    const { srcDoc, viewportWidth, contentHeight, onMeasure, onHeightChange } = props;

    const containerRef = useRef<HTMLDivElement | null>(null);
    const contentObserverRef = useRef<ResizeObserver | null>(null);
    /** The document the frame currently shows; `null` once unmounted. */
    const activeSrcDocRef = useRef<string | null>(srcDoc);
    const [width, setWidth] = useState(0);

    // Read the width before paint, so the card doesn't flash at zero
    // height and then expand.
    useLayoutEffect(() => {
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

    // Stop watching the previous document when the source changes, and on
    // unmount.
    useEffect(() => {
        activeSrcDocRef.current = srcDoc;

        return () => {
            activeSrcDocRef.current = null;
            contentObserverRef.current?.disconnect();
            contentObserverRef.current = null;
        };
    }, [srcDoc]);

    const scale = width > 0 ? width / viewportWidth : 0;
    const maxHeight = width * PATTERN_PREVIEW_MAX_HEIGHT_RATIO;
    const frameHeight =
        contentHeight === null
            ? maxHeight
            : Math.min(contentHeight * scale, maxHeight);

    useEffect(() => {
        if (frameHeight > 0) {
            onHeightChange?.(frameHeight);
        }
    }, [frameHeight, onHeightChange]);

    const handleLoad = (event: React.SyntheticEvent<HTMLIFrameElement>): void => {
        const doc = event.currentTarget.contentDocument;

        if (doc === null || doc === undefined) {
            return;
        }

        const loadedSrcDoc = srcDoc;
        const measure = (): void => {
            if (activeSrcDocRef.current !== loadedSrcDoc) {
                return;
            }

            const height = measurePreviewDocument(doc);

            if (height > 0) {
                onMeasure(loadedSrcDoc, height);
            }
        };

        measure();

        // Re-measure as fonts and images settle. The frame is same-origin,
        // so its document can be observed directly; the frame's own
        // `ResizeObserver` is preferred so observations follow its
        // rendering.
        contentObserverRef.current?.disconnect();
        contentObserverRef.current = null;

        const frameWindow = doc.defaultView as
            | (Window & { ResizeObserver?: typeof ResizeObserver })
            | null;
        const Observer =
            frameWindow?.ResizeObserver ??
            (typeof ResizeObserver === 'undefined' ? undefined : ResizeObserver);

        if (Observer !== undefined && doc.documentElement !== null) {
            const observer = new Observer(measure);

            observer.observe(doc.documentElement);
            contentObserverRef.current = observer;
        }

        void doc.fonts?.ready.then(measure);
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

/**
 * Mounts the card's iframe through the shared frame queue once it has a
 * result and is near its scroll root, and unmounts it when it scrolls far
 * away.
 */
function useFrameMounted(wanted: boolean): boolean {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        if (!wanted) {
            setMounted(false);

            return undefined;
        }

        if (mounted) {
            return undefined;
        }

        return schedulePreviewMount(() => setMounted(true));
    }, [wanted, mounted]);

    return mounted && wanted;
}

export function PatternPreview(props: PatternPreviewProps): JSX.Element {
    const { pattern, title, apiBase, scrollRoot } = props;

    const wrapperRef = useRef<HTMLDivElement | null>(null);
    const getRoot = useScrollRoot(scrollRoot);
    const empty = isEmptyPattern(pattern);
    const canLoad = !empty && typeof apiBase === 'string' && apiBase !== '';
    const inView = useHasBeenInView(wrapperRef, canLoad, getRoot);
    const result = usePatternPreviewResult(apiBase, pattern, canLoad && inView);
    const near = useIsNearScrollRoot(wrapperRef, result !== null, getRoot);
    const frameMounted = useFrameMounted(result !== null && near);
    const [parkedHeight, setParkedHeight] = useState<number | null>(null);
    const [measurement, setMeasurement] = useState<{
        readonly srcDoc: string;
        readonly height: number;
    } | null>(null);

    const srcDoc = useMemo(
        () => (result === null ? null : buildPreviewDocument(result)),
        [result]
    );

    // A height measured for an earlier document doesn't apply to a new
    // one: the frame falls back to the cap until the new document loads.
    const contentHeight =
        measurement !== null && measurement.srcDoc === srcDoc ? measurement.height : null;

    const handleMeasure = useCallback((measuredSrcDoc: string, height: number): void => {
        setMeasurement((current) =>
            current !== null && current.srcDoc === measuredSrcDoc && current.height === height
                ? current
                : { srcDoc: measuredSrcDoc, height }
        );
    }, []);

    let state: 'fallback' | 'rendered' | 'parked' = 'fallback';

    if (result !== null && frameMounted) {
        state = 'rendered';
    } else if (result !== null && parkedHeight !== null) {
        state = 'parked';
    }

    return (
        <div
            ref={wrapperRef}
            className="ap-pattern-preview-slot"
            data-testid="ap-pattern-preview-slot"
            data-preview-state={state}
            aria-hidden="true"
            // `inert` keeps the iframe and anything inside it out of focus
            // and the accessibility tree.
            {...INERT_PROPS}
        >
            {state === 'rendered' && srcDoc !== null ? (
                <PreviewFrame
                    srcDoc={srcDoc}
                    viewportWidth={patternViewportWidth(pattern)}
                    contentHeight={contentHeight}
                    onMeasure={handleMeasure}
                    onHeightChange={setParkedHeight}
                />
            ) : null}
            {state === 'parked' ? (
                // Holds the card's measured height while its iframe is
                // unmounted, so scrolling doesn't shift the list.
                <div
                    className="ap-pattern-preview ap-pattern-preview--parked"
                    data-testid="ap-pattern-preview-parked"
                    style={{ height: `${parkedHeight}px` }}
                />
            ) : null}
            {state === 'fallback' ? (
                <PatternThumbnail
                    blocks={pattern.content?.blocks ?? []}
                    rawContent={pattern.content?.raw}
                    title={title}
                />
            ) : null}
        </div>
    );
}
