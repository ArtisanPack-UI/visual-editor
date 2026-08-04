import '@testing-library/jest-dom/vitest';

/**
 * Restore `localStorage` / `sessionStorage` when the test global has been
 * left holding an inert stub.
 *
 * jsdom implements Web Storage correctly, but vitest never copies it onto
 * the test global on newer Node. `getWindowKeys()` filters jsdom's window
 * properties with `if (k in global) return KEYS.includes(k)` — i.e. a key
 * that already exists on Node's `globalThis` is only copied when it is in
 * vitest's own allow-list, and `localStorage` is not on that list.
 *
 * Node 22+ ships an experimental Web Storage global whose accessor returns
 * `undefined` unless the process was started with `--localstorage-file`.
 * So on those versions the key *is* in `globalThis`, vitest skips it, and
 * jsdom's working implementation is shadowed by Node's inert one:
 * `'localStorage' in window` is `true` while `window.localStorage` is
 * `undefined`. On older Node the key is absent, vitest copies jsdom's
 * version, and everything works — which is why this only appears after a
 * Node upgrade rather than a code change.
 *
 * The shim below is a plain in-memory `Storage`. It deliberately does not
 * implement the named-property access an index proxy would give you
 * (`storage.foo = 'bar'`); nothing in this codebase uses that form, and a
 * Proxy here would buy complexity for no coverage.
 */
function createMemoryStorage(): Storage {
    const entries = new Map<string, string>();

    return {
        get length(): number {
            return entries.size;
        },
        key(index: number): string | null {
            return Array.from(entries.keys())[index] ?? null;
        },
        getItem(key: string): string | null {
            return entries.get(String(key)) ?? null;
        },
        setItem(key: string, value: string): void {
            entries.set(String(key), String(value));
        },
        removeItem(key: string): void {
            entries.delete(String(key));
        },
        clear(): void {
            entries.clear();
        },
    } as Storage;
}

if (typeof window !== 'undefined') {
    for (const area of ['localStorage', 'sessionStorage'] as const) {
        // Only step in when the global is genuinely unusable. When jsdom's
        // implementation did survive, leave it alone — it is the real
        // thing, and it is shared with `document`'s storage events.
        if (window[area] !== undefined) {
            continue;
        }

        // `defineProperty`, not assignment: the property Node leaves behind
        // is an accessor whose setter would swallow a plain write.
        Object.defineProperty(window, area, {
            configurable: true,
            writable: true,
            value: createMemoryStorage(),
        });
    }
}

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
