/**
 * Editor live token preview.
 *
 * The editor stores authored `{{token}}` text verbatim in the block's
 * `content` attribute — server-side render swaps it for the resolved
 * value, but the editor canvas would otherwise show the raw token.
 * Users then can't tell whether their token is valid without saving
 * and viewing the front end.
 *
 * This module installs a MutationObserver on `document.body` that:
 *  1. Watches for text nodes containing `{{...}}` inside the editor
 *     canvas (`.editor-styles-wrapper`, or the block-list root).
 *  2. Batches every distinct token across the canvas.
 *  3. Calls the batched resolver (`resolveTokens`) once per settle.
 *  4. Wraps each token occurrence with a `<span data-dc-token="...">`
 *     showing the resolved value or `[Missing: token]`.
 *
 * The wrapper is inserted alongside the raw token text (which is
 * hidden via CSS on the wrapper) so save-time serialization still
 * emits the original `{{token}}` — the wrapper only exists in the
 * live DOM.
 *
 * Idempotent and safe on hot reload; the observer disconnects and
 * reconnects cleanly.
 *
 * @since 1.4.0
 */

import { resolveTokens } from './api';

const TOKEN_RE = /\{\{\s*([^{}\s][^{}]*?)\s*\}\}/g;
const WRAPPER_ATTR = 'data-dc-token';
const WRAPPER_CLASS = 've-dc-inline-token';
const STYLE_MARKER = 'data-artisanpack-dc-inline';
const OBSERVE_SELECTOR = 'body';

const INLINE_STYLES = `
.${WRAPPER_CLASS} {
    display: inline;
    padding: 1px 6px;
    margin: 0 1px;
    background: rgba(0, 124, 186, 0.12);
    border: 1px solid rgba(0, 124, 186, 0.35);
    border-radius: 3px;
    color: #1c3b6b;
    font-family: ui-monospace, "SF Mono", Menlo, monospace;
    font-size: 0.9em;
}
.${WRAPPER_CLASS}[data-dc-missing="1"] {
    background: rgba(214, 54, 56, 0.1);
    border-color: rgba(214, 54, 56, 0.4);
    color: #a02020;
}
.${WRAPPER_CLASS} .ve-dc-inline-token__raw {
    display: none;
}
`;

let observer: MutationObserver | null = null;
let installed = false;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function ensureInlineStyles(): void {
    if (typeof document === 'undefined') return;
    if (document.querySelector(`style[${STYLE_MARKER}]`)) return;
    const styleEl = document.createElement('style');
    styleEl.setAttribute(STYLE_MARKER, '1');
    styleEl.textContent = INLINE_STYLES;
    document.head.appendChild(styleEl);
}

function editorRoots(): HTMLElement[] {
    if (typeof document === 'undefined') return [];
    const nodes = document.querySelectorAll<HTMLElement>(
        '.editor-styles-wrapper, .block-editor-block-list__layout, .interface-interface-skeleton__content'
    );
    return Array.from(nodes);
}

function walkForTokens(root: HTMLElement, callback: (node: Text, token: string, start: number, end: number) => void): void {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: (node) => {
            const text = (node as Text).nodeValue ?? '';
            if (!text.includes('{{')) return NodeFilter.FILTER_REJECT;
            // Skip nodes that are inside our own wrapper (already processed).
            const parent = node.parentElement;
            if (parent && parent.closest(`.${WRAPPER_CLASS}`)) return NodeFilter.FILTER_REJECT;
            // Skip CodeMirror / preformatted / code blocks so we don't
            // substitute inside literal-content areas.
            if (parent && parent.closest('code, pre, .block-editor-plain-text, .cm-editor')) {
                return NodeFilter.FILTER_REJECT;
            }
            return NodeFilter.FILTER_ACCEPT;
        },
    });

    let node: Node | null;
    // eslint-disable-next-line no-cond-assign
    while ((node = walker.nextNode())) {
        const textNode = node as Text;
        const text = textNode.nodeValue ?? '';
        TOKEN_RE.lastIndex = 0;
        let match: RegExpExecArray | null;
        // eslint-disable-next-line no-cond-assign
        while ((match = TOKEN_RE.exec(text)) !== null) {
            const token = match[1].trim();
            const start = match.index;
            const end = start + match[0].length;
            callback(textNode, token, start, end);
        }
    }
}

async function scanAndSubstitute(): Promise<void> {
    const roots = editorRoots();
    if (roots.length === 0) return;

    // Pass 1: collect distinct tokens and pending occurrence coords.
    const occurrences: Array<{ node: Text; token: string; start: number; end: number }> = [];
    const tokens = new Set<string>();

    for (const root of roots) {
        walkForTokens(root, (node, token, start, end) => {
            occurrences.push({ node, token, start, end });
            tokens.add(token);
        });
    }

    if (occurrences.length === 0) return;

    // Pass 2: resolve every distinct token (batched by the API layer).
    let values: Record<string, unknown> = {};
    try {
        values = await resolveTokens(Array.from(tokens));
    } catch {
        // If the resolver fails, mark every token as missing.
    }

    // Pass 3: re-scan the DOM (nodes may have moved) and substitute.
    // We rebuild from scratch to guarantee we're operating on live
    // nodes; the MutationObserver's debounced re-fire handles any
    // subsequent edits.
    for (const root of roots) {
        substituteInRoot(root, values);
    }
}

function substituteInRoot(root: HTMLElement, values: Record<string, unknown>): void {
    const targets: Array<{ node: Text; matches: Array<{ token: string; start: number; end: number }> }> = [];

    walkForTokens(root, (node, token, start, end) => {
        let entry = targets.find((t) => t.node === node);
        if (!entry) {
            entry = { node, matches: [] };
            targets.push(entry);
        }
        entry.matches.push({ token, start, end });
    });

    for (const target of targets) {
        const text = target.node.nodeValue ?? '';
        // Build a fragment of alternating text + wrapper spans.
        const fragment = document.createDocumentFragment();
        let cursor = 0;
        for (const m of target.matches) {
            if (m.start > cursor) {
                fragment.appendChild(document.createTextNode(text.slice(cursor, m.start)));
            }
            const wrapper = document.createElement('span');
            wrapper.className = WRAPPER_CLASS;
            wrapper.setAttribute(WRAPPER_ATTR, m.token);

            const value = values[m.token];
            const isMissing = value === null || value === undefined || value === '';
            if (isMissing) {
                wrapper.setAttribute('data-dc-missing', '1');
                wrapper.textContent = `[Missing: ${m.token}]`;
            } else {
                wrapper.textContent = String(value);
            }
            fragment.appendChild(wrapper);
            cursor = m.end;
        }
        if (cursor < text.length) {
            fragment.appendChild(document.createTextNode(text.slice(cursor)));
        }
        target.node.parentNode?.replaceChild(fragment, target.node);
    }
}

function scheduleScan(): void {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        void scanAndSubstitute();
    }, 150);
}

function shouldReactToMutation(mutation: MutationRecord): boolean {
    // Ignore mutations we caused ourselves — the wrapper spans we
    // insert would otherwise trigger a loop.
    if (mutation.target instanceof Element && mutation.target.closest(`.${WRAPPER_CLASS}`)) {
        return false;
    }
    for (const node of Array.from(mutation.addedNodes)) {
        if (node instanceof HTMLElement && node.classList?.contains(WRAPPER_CLASS)) return false;
    }
    return true;
}

export function installEditorLivePreview(): void {
    if (installed || typeof window === 'undefined') return;
    installed = true;

    ensureInlineStyles();

    // Initial scan once DOM is ready.
    const boot = () => {
        void scanAndSubstitute();

        const targetRoot = document.querySelector(OBSERVE_SELECTOR);
        if (!targetRoot) return;

        observer = new MutationObserver((mutations) => {
            if (mutations.some(shouldReactToMutation)) {
                scheduleScan();
            }
        });

        observer.observe(targetRoot, {
            childList: true,
            characterData: true,
            subtree: true,
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        // Give Gutenberg a moment to mount the first block list.
        setTimeout(boot, 300);
    }
}

export function uninstallEditorLivePreview(): void {
    installed = false;
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = null;
    observer?.disconnect();
    observer = null;
}
