/**
 * Renders the compiled global-styles CSS the host fetched from
 * `GET /visual-editor/api/global-styles/{id}` (or pre-rendered with the
 * PHP `GlobalStylesCssProvider`) as an inline `<style>` tag.
 *
 * The renderer is dumb on purpose: it does not know how to compile the
 * theme.json payload. The PHP `GlobalStylesCompiler` is the single
 * source of truth for what gets emitted on the front-end so all three
 * renderers (Blade, React, Vue) stay byte-identical — that is the
 * canvas/published parity guarantee #378 closes.
 *
 * Apps wire it once at the top of their layout (or pass the same CSS
 * string via `BlockTree`'s `globalStylesCss` prop, which delegates here)
 * so a page with multiple `<BlockTree>` instances does not inject the
 * same `<style>` block twice.
 */

import type { ReactElement } from 'react';

export interface GlobalStylesProps {
    css: string | null | undefined;
}

export function GlobalStyles({ css }: GlobalStylesProps): ReactElement | null {
    if (typeof css !== 'string' || css === '') {
        return null;
    }

    // Pass an explicit empty string (rather than React's bare-attribute
    // shorthand) so SSR serializes `data-ve-global-styles=""` —
    // byte-identical to Vue's `h('style', { 'data-ve-global-styles': '' })`
    // output. The renderer parity test asserts on the exact bytes.
    return (
        <style
            data-ve-global-styles=""
            dangerouslySetInnerHTML={{ __html: css }}
        />
    );
}
