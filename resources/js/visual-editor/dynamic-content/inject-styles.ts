/**
 * Runtime stylesheet injection for the Dynamic Content editor UX.
 *
 * The parent editor bundle has an unpredictable Vite CSS-import
 * traversal for deep transitive imports — the stylesheet lands
 * inconsistently depending on which host app consumes the package.
 * Injecting the styles at runtime via a single <style> tag sidesteps
 * that entirely and guarantees the modal, chip decoration, snippet
 * placeholder, and link picker DC tab look right regardless of the
 * host's build config.
 *
 * Idempotent: the style element is keyed by `data-artisanpack-dc="1"`
 * so subsequent calls (HMR, dual editor mount) don't stack.
 *
 * @since 1.4.0
 */

const STYLE_TAG_MARKER = 'data-artisanpack-dc';

const STYLES = `
/* Token Inserter modal */
.ve-dc-token-inserter .ve-dc-token-inserter__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 300px;
    max-height: 60vh;
}
.ve-dc-token-inserter .ve-dc-token-inserter__list {
    flex: 1 1 auto;
    overflow-y: auto;
    border: 1px solid var(--wp-admin-theme-color-darker-10, #ddd);
    border-radius: 4px;
    padding: 8px;
    background: #fff;
}
.ve-dc-token-inserter .ve-dc-token-inserter__list ul {
    margin: 0;
    padding: 0;
    list-style: none;
}
.ve-dc-token-group { margin-bottom: 16px; }
.ve-dc-token-group:last-child { margin-bottom: 0; }
.ve-dc-token-group__label {
    margin: 0 0 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #757575;
}
.ve-dc-token-option {
    display: grid;
    grid-template-columns: 1fr auto auto;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 8px 10px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    text-align: left;
    color: inherit;
    font: inherit;
    transition: background-color 60ms ease, border-color 60ms ease;
}
.ve-dc-token-option:hover { background: rgba(0, 0, 0, 0.04); }
.ve-dc-token-option.is-selected {
    background: rgba(0, 124, 186, 0.08);
    border-color: var(--wp-admin-theme-color, #007cba);
}
.ve-dc-token-option__label { font-weight: 500; }
.ve-dc-token-option__code {
    font-family: ui-monospace, "SF Mono", Menlo, monospace;
    font-size: 12px;
    padding: 2px 6px;
    background: #f0f0f1;
    border-radius: 3px;
    color: #1e1e1e;
    white-space: nowrap;
}
.ve-dc-token-option__type {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    background: #e5e5e5;
    color: #595959;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.ve-dc-token-option__type--phone,
.ve-dc-token-option__type--email { background: #dcf1e2; color: #275f36; }
.ve-dc-token-option__type--url { background: #dbe8fb; color: #1c3b6b; }
.ve-dc-token-option__type--image { background: #fbe9d0; color: #6b3d0c; }
.ve-dc-token-option__type--number { background: #efe0f7; color: #5b2a7d; }
.ve-dc-token-inserter__preview {
    padding: 10px 12px;
    background: #f6f7f7;
    border-left: 3px solid var(--wp-admin-theme-color, #007cba);
    border-radius: 2px;
    font-size: 13px;
    line-height: 1.5;
}
.ve-dc-token-inserter__actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #ddd;
}

/* Snippet block placeholder */
.ve-snippet-block {
    border: 1px dashed #bbb;
    padding: 12px;
    border-radius: 4px;
    background: #fafafa;
}
.ve-snippet-placeholder {
    color: #757575;
    font-style: italic;
    text-align: center;
    padding: 16px;
}
.ve-snippet-preview { padding: 8px 12px; }
.ve-snippet-preview__label {
    font-size: 12px;
    color: #595959;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Dynamic Loop block placeholder */
.ve-dynamic-loop-block {
    border: 1px dashed #bbb;
    padding: 12px;
    border-radius: 4px;
    background: #fafafa;
}
.ve-dynamic-loop__template {
    padding: 8px;
    background: #fff;
    border-radius: 3px;
    margin-top: 8px;
    outline: 1px solid #e5e5e5;
}

`;

let injected = false;

export function injectDynamicContentStyles(): void {
    if (injected) return;
    if (typeof document === 'undefined') return;

    // Idempotent — an already-present marker means an earlier boot
    // already injected the styles.
    if (document.querySelector(`style[${STYLE_TAG_MARKER}]`)) {
        injected = true;
        return;
    }

    const styleEl = document.createElement('style');
    styleEl.setAttribute(STYLE_TAG_MARKER, '1');
    styleEl.textContent = STYLES;
    document.head.appendChild(styleEl);

    injected = true;
}
