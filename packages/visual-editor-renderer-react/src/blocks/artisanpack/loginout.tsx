/**
 * Loginout renderer (#522) — React.
 *
 * Auth-state-aware dynamic block. Reads the `_resolved*` envelope
 * stamped by the host's server-side pipeline (LoginoutResolver on the
 * Blade renderer side, the consumer's own auth-state plumbing on the
 * React side) and emits the matching link — or the pre-rendered
 * login form when the block opts into the form view AND the viewer
 * is logged-out. Byte-shape parity with the Blade and Vue
 * counterparts.
 */

import type { JSX } from 'react';

import { attrBoolean, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

export function LoginoutBlock({ attributes }: BlockRendererProps): JSX.Element {
    const isUserLoggedIn = attrBoolean(attributes._resolvedIsUserLoggedIn, false);
    const displayLoginAsForm = attrBoolean(attributes.displayLoginAsForm, false);
    const loginFormHtml = attrString(attributes._resolvedLoginFormHtml);
    const showForm = !isUserLoggedIn && displayLoginAsForm && loginFormHtml !== '';

    const resolvedClass = attrString(
        attributes._resolvedLoginoutClass,
        isUserLoggedIn ? 'logged-in' : showForm ? 'logged-out has-login-form' : 'logged-out'
    );
    const className = attrString(attributes.className);
    const classes = classList([resolvedClass, className]);

    if (showForm) {
        return (
            <div
                className={classes}
                // The login form HTML comes from a host-side filter; the
                // consumer is responsible for sanitizing it. Mirrors
                // upstream's behaviour of emitting `wp_login_form()` as
                // raw HTML inside the wrapper.
                dangerouslySetInnerHTML={{ __html: loginFormHtml }}
            />
        );
    }

    const url = safeUrl(attrString(attributes._resolvedLoginoutUrl));
    const label = attrString(
        attributes._resolvedLoginoutLabel,
        isUserLoggedIn ? 'Log out' : 'Log in'
    );

    if (url === '') {
        return <div className={classes}>{label}</div>;
    }

    return (
        <div className={classes}>
            <a href={url}>{label}</a>
        </div>
    );
}
