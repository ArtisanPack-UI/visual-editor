/**
 * Front-end interactivity for the interactive block families.
 *
 * Currently wires up three block families:
 *
 * - `artisanpack/accordion` — click / Enter / Space toggles the panel
 *   open and closed; mirrors `aria-expanded` and the `hidden` attribute
 *   on the body so screen readers and styling react together.
 * - `artisanpack/tabs` — click activates a tab; arrow keys, Home, and
 *   End move between tabs roving-tabindex style; `hidden` is toggled on
 *   each panel so only the active one shows.
 * - `artisanpack/social-share-content` copy-link chip (#963) — the
 *   renderers emit a plain `<a href="{permalink}" data-ap-copy-link>`
 *   so the chip still works without JavaScript; this upgrades it to a
 *   `<button type="button">` that writes the permalink to the clipboard
 *   and announces the result through a polite live region.
 *
 * Vanilla DOM only — no framework dependencies. The script is
 * idempotent: it can run more than once on the same DOM and skips
 * elements it has already bound, so it plays nicely with SPA
 * navigation that re-runs scripts after replacing markup. Dispatch
 * `window.dispatchEvent(new Event('ap:rebind'))` after navigation to
 * pick up freshly-rendered blocks.
 */

(function () {
    'use strict';

    var INIT_FLAG = '__apBlockInteractivityBound';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function bindAccordion(root) {
        if (root[INIT_FLAG]) {
            return;
        }
        root[INIT_FLAG] = true;

        var trigger = root.querySelector('.ap-accordion__title-content[role="button"]');
        var panel = root.querySelector('.ap-accordion__body[role="region"]');

        if (!trigger || !panel) {
            return;
        }

        function setOpen(open) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        }

        setOpen(trigger.getAttribute('aria-expanded') === 'true');

        trigger.addEventListener('click', function () {
            setOpen(trigger.getAttribute('aria-expanded') !== 'true');
        });

        trigger.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                event.preventDefault();
                setOpen(trigger.getAttribute('aria-expanded') !== 'true');
            }
        });
    }

    function bindTabs(root) {
        if (root[INIT_FLAG]) {
            return;
        }
        root[INIT_FLAG] = true;

        var tablist = root.querySelector('[role="tablist"]');
        if (!tablist) {
            return;
        }

        var triggers = Array.prototype.slice.call(
            tablist.querySelectorAll('[role="tab"]')
        );
        var panels = Array.prototype.slice.call(
            root.querySelectorAll('.ap-tab-section[role="tabpanel"]')
        );

        if (triggers.length === 0 || panels.length === 0) {
            return;
        }

        function activate(index) {
            triggers.forEach(function (trigger, triggerIndex) {
                var selected = triggerIndex === index;
                trigger.setAttribute('aria-selected', selected ? 'true' : 'false');
                trigger.setAttribute('tabindex', selected ? '0' : '-1');
                if (selected) {
                    trigger.focus({ preventScroll: true });
                }
            });

            panels.forEach(function (panel, panelIndex) {
                if (panelIndex === index) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', '');
                }
            });
        }

        // Honor whatever initial aria-selected the renderer stamped
        // (defaults to the first trigger; a host can pre-select a
        // different one). If none is marked selected, fall back to
        // index 0.
        var initialIndex = 0;
        for (var t = 0; t < triggers.length; t += 1) {
            if (triggers[t].getAttribute('aria-selected') === 'true') {
                initialIndex = t;
                break;
            }
        }

        panels.forEach(function (panel, panelIndex) {
            if (panelIndex !== initialIndex) {
                panel.setAttribute('hidden', '');
            }
        });

        triggers.forEach(function (trigger, triggerIndex) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                activate(triggerIndex);
            });

            trigger.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                    event.preventDefault();
                    activate((triggerIndex + 1) % triggers.length);
                } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    activate((triggerIndex - 1 + triggers.length) % triggers.length);
                } else if (event.key === 'Home') {
                    event.preventDefault();
                    activate(0);
                } else if (event.key === 'End') {
                    event.preventDefault();
                    activate(triggers.length - 1);
                }
            });
        });
    }

    var COPY_LINK_MESSAGES = {
        copied: 'Link copied',
        failed: 'Could not copy link',
    };

    var COPY_LINK_RESET_MS = 4000;

    function legacyCopy(text) {
        var field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.top = '0';
        field.style.left = '0';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();

        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(field);

        return copied;
    }

    function copyText(text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text).catch(function () {
                if (!legacyCopy(text)) {
                    throw new Error('copy failed');
                }
            });
        }

        return new Promise(function (resolve, reject) {
            if (legacyCopy(text)) {
                resolve();
            } else {
                reject(new Error('copy failed'));
            }
        });
    }

    function bindCopyLink(link) {
        if (link[INIT_FLAG]) {
            return;
        }
        link[INIT_FLAG] = true;

        var url = link.getAttribute('data-ap-copy-link');
        var parent = link.parentNode;

        if (!url || !parent) {
            return;
        }

        // Swap the fallback link for a real button carrying the same
        // classes, inline color properties, accessible name and data
        // attribute, so it keeps its chip styling.
        var button = document.createElement('button');
        button.type = 'button';
        for (var a = 0; a < link.attributes.length; a += 1) {
            var attribute = link.attributes[a];
            if (attribute.name !== 'href') {
                button.setAttribute(attribute.name, attribute.value);
            }
        }
        while (link.firstChild) {
            button.appendChild(link.firstChild);
        }
        button[INIT_FLAG] = true;

        var status = document.createElement('span');
        status.className = 'ap-social-share-content__status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');

        parent.replaceChild(button, link);
        parent.insertBefore(status, button.nextSibling);

        var resetTimer = null;

        function announce(message, copied) {
            if (resetTimer) {
                window.clearTimeout(resetTimer);
            }

            // Clear first so a repeat click re-announces the same text.
            status.textContent = '';
            window.setTimeout(function () {
                status.textContent = message;
            }, 50);

            if (copied) {
                button.classList.add('is-copied');
            } else {
                button.classList.remove('is-copied');
            }

            resetTimer = window.setTimeout(function () {
                status.textContent = '';
                button.classList.remove('is-copied');
                resetTimer = null;
            }, COPY_LINK_RESET_MS);
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            copyText(url).then(
                function () {
                    announce(COPY_LINK_MESSAGES.copied, true);
                },
                function () {
                    announce(COPY_LINK_MESSAGES.failed, false);
                }
            );
        });
    }

    function init() {
        var accordions = document.querySelectorAll('.ap-accordion');
        for (var i = 0; i < accordions.length; i += 1) {
            bindAccordion(accordions[i]);
        }

        var tabs = document.querySelectorAll('[data-ap-tabs]');
        for (var j = 0; j < tabs.length; j += 1) {
            bindTabs(tabs[j]);
        }

        var copyLinks = document.querySelectorAll('a[data-ap-copy-link]');
        for (var k = 0; k < copyLinks.length; k += 1) {
            bindCopyLink(copyLinks[k]);
        }
    }

    ready(init);

    window.addEventListener('ap:rebind', init);
})();
