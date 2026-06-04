/**
 * Loginout renderer (#522) — Vue.
 *
 * Auth-state-aware dynamic block mirroring the Blade + React
 * implementations. Reads the `_resolved*` envelope stamped by the
 * host's server-side pipeline and emits the matching link — or the
 * pre-rendered login form when the block opts into the form view
 * AND the viewer is logged-out.
 */

import { defineComponent, h } from 'vue';

import { attrBoolean, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

export const LoginoutBlock = defineComponent({
    name: 'LoginoutBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const attributes = props.attributes;
            const isUserLoggedIn = attrBoolean(attributes._resolvedIsUserLoggedIn, false);
            const displayLoginAsForm = attrBoolean(attributes.displayLoginAsForm, false);
            const loginFormHtml = attrString(attributes._resolvedLoginFormHtml);
            const showForm =
                !isUserLoggedIn && displayLoginAsForm && loginFormHtml !== '';

            const resolvedClass = attrString(
                attributes._resolvedLoginoutClass,
                isUserLoggedIn
                    ? 'logged-in'
                    : showForm
                      ? 'logged-out has-login-form'
                      : 'logged-out'
            );
            const className = attrString(attributes.className);
            const classes = classList([resolvedClass, className]);

            if (showForm) {
                // The login form HTML comes from a host-side filter;
                // the consumer is responsible for sanitizing it.
                // Mirrors upstream's behaviour of emitting
                // `wp_login_form()` as raw HTML inside the wrapper.
                return h('div', {
                    class: classes,
                    innerHTML: loginFormHtml,
                });
            }

            const url = safeUrl(attrString(attributes._resolvedLoginoutUrl));
            const label = attrString(
                attributes._resolvedLoginoutLabel,
                isUserLoggedIn ? 'Log out' : 'Log in'
            );

            if (url === '') {
                return h('div', { class: classes }, label);
            }

            return h('div', { class: classes }, [h('a', { href: url }, label)]);
        };
    },
});
