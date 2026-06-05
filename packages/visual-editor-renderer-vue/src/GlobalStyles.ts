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

import { defineComponent, h } from 'vue';
import type { PropType, VNode } from 'vue';

export interface GlobalStylesProps {
    css: string | null | undefined;
}

export const GlobalStyles = defineComponent({
    name: 'GlobalStyles',
    props: {
        css: {
            type: String as PropType<string | null | undefined>,
            default: null,
        },
    },
    setup(props) {
        return (): VNode | null => {
            if (typeof props.css !== 'string' || props.css === '') {
                return null;
            }

            return h(
                'style',
                {
                    'data-ve-global-styles': '',
                    innerHTML: props.css,
                },
                []
            );
        };
    },
});
