/**
 * Front-end interactivity for the interactive block families.
 *
 * Currently wires up two block families:
 *
 * - `artisanpack/accordion` — click / Enter / Space toggles the panel
 *   open and closed; mirrors `aria-expanded` and the `hidden` attribute
 *   on the body so screen readers and styling react together.
 * - `artisanpack/tabs` — click activates a tab; arrow keys, Home, and
 *   End move between tabs roving-tabindex style; `hidden` is toggled on
 *   each panel so only the active one shows.
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

    function init() {
        var accordions = document.querySelectorAll('.ap-accordion');
        for (var i = 0; i < accordions.length; i += 1) {
            bindAccordion(accordions[i]);
        }

        var tabs = document.querySelectorAll('[data-ap-tabs]');
        for (var j = 0; j < tabs.length; j += 1) {
            bindTabs(tabs[j]);
        }
    }

    ready(init);

    window.addEventListener('ap:rebind', init);
})();
