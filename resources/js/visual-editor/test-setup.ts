import '@testing-library/jest-dom/vitest';

// `@wordpress/components` calls `window.matchMedia` through its responsive
// helpers (e.g. `PanelBody`, `SelectControl`). jsdom doesn't ship a
// `matchMedia` implementation so the component import throws. Install a
// no-op stub that satisfies the `MediaQueryList` shape.
if (typeof window !== 'undefined' && typeof window.matchMedia !== 'function') {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: (query: string): MediaQueryList =>
            ({
                matches: false,
                media: query,
                onchange: null,
                addListener: () => {},
                removeListener: () => {},
                addEventListener: () => {},
                removeEventListener: () => {},
                dispatchEvent: () => false,
            }) as MediaQueryList,
    });
}

// jsdom does not implement Range.getBoundingClientRect or
// Document.elementFromPoint, which ProseMirror needs for mouse/selection
// handling. Stub just enough for Tiptap-driven tests to run.
if (typeof document !== 'undefined') {
    if (typeof document.elementFromPoint !== 'function') {
        document.elementFromPoint = () => null;
    }

    if (typeof Range !== 'undefined' && !Range.prototype.getBoundingClientRect) {
        Range.prototype.getBoundingClientRect = function () {
            return {
                x: 0,
                y: 0,
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                width: 0,
                height: 0,
                toJSON: () => ({}),
            } as DOMRect;
        };
    }

    if (typeof Range !== 'undefined' && !Range.prototype.getClientRects) {
        Range.prototype.getClientRects = function () {
            return {
                length: 0,
                item: () => null,
                [Symbol.iterator]: function* () {},
            } as unknown as DOMRectList;
        };
    }
}
