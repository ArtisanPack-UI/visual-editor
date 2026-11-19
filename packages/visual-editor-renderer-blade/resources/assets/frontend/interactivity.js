/**
 * Front-end interactivity for the interactive block families.
 *
 * Currently wires up four block families:
 *
 * - `artisanpack/accordion` — click / Enter / Space toggles the panel
 *   open and closed; mirrors `aria-expanded` and the `hidden` attribute
 *   on the body so screen readers and styling react together.
 * - `artisanpack/tabs` — click activates a tab; arrow keys, Home, and
 *   End move between tabs roving-tabindex style; `hidden` is toggled on
 *   each panel so only the active one shows.
 * - `artisanpack/carousel` (#1127) — reveals the controls, builds one
 *   dot per position, slides the track on arrow / dot clicks, arrow
 *   keys, Home / End and swipe or drag, and keeps the slides outside
 *   the view inert. Optional loop and autoplay; autoplay never runs
 *   under `prefers-reduced-motion`, pauses on hover and stops when
 *   anything in the carousel gets focus.
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

    var CAROUSEL_CLASS = 'wp-block-artisanpack-carousel';
    var CAROUSEL_SLIDE_CLASS = 'wp-block-artisanpack-carousel-slide';
    var CAROUSEL_SKIP_KEYS_IN = 'input, textarea, select, [contenteditable], [role="slider"]';
    // Share of a slide's width a drag must cover before it moves on.
    var CAROUSEL_DRAG_THRESHOLD = 0.2;

    function reducedMotionQuery() {
        return window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    }

    function childWithClass(parent, className) {
        if (!parent) {
            return null;
        }
        for (var i = 0; i < parent.children.length; i += 1) {
            if (parent.children[i].classList.contains(className)) {
                return parent.children[i];
            }
        }
        return null;
    }

    function bindCarousel(root) {
        if (root[INIT_FLAG]) {
            return;
        }
        root[INIT_FLAG] = true;

        // Direct children only, so a carousel nested in a slide keeps
        // its own controls.
        var viewport = childWithClass(root, CAROUSEL_CLASS + '__viewport');
        var track = childWithClass(viewport, CAROUSEL_CLASS + '__track');

        if (!track) {
            return;
        }

        var allSlides = Array.prototype.filter.call(track.children, function (child) {
            return child.classList.contains(CAROUSEL_SLIDE_CLASS);
        });
        // The slides on screen: a screen-size visibility rule can hide a
        // slide at some widths (`display: none` on the slide itself).
        var slides = allSlides;

        if (slides.length === 0) {
            return;
        }

        var controls = childWithClass(root, CAROUSEL_CLASS + '__controls');
        var prev = childWithClass(controls, CAROUSEL_CLASS + '__arrow--previous');
        var next = childWithClass(controls, CAROUSEL_CLASS + '__arrow--next');
        var dotsHost = childWithClass(controls, CAROUSEL_CLASS + '__dots');
        var toggle = childWithClass(controls, CAROUSEL_CLASS + '__autoplay');
        var loop = root.getAttribute('data-loop') === 'true';
        var intervalSeconds = parseInt(root.getAttribute('data-autoplay') || '', 10);
        var hasAutoplay = isFinite(intervalSeconds) && intervalSeconds > 0;
        var motionQuery = hasAutoplay ? reducedMotionQuery() : null;

        // Read live, so turning reduced motion on mid-visit stops autoplay
        // and turning it off lets it resume.
        function canAutoplay() {
            return hasAutoplay && !(motionQuery && motionQuery.matches);
        }

        var index = 0;
        var perView = 1;
        var dots = [];
        var timer = null;
        var userPaused = false;
        var hoverPaused = false;

        function isRtl() {
            return window.getComputedStyle(root).direction === 'rtl';
        }

        // The stylesheet resolves the slides per view for the current
        // width into `--ap-carousel-columns`.
        function readPerView() {
            var value = parseInt(window.getComputedStyle(root).getPropertyValue('--ap-carousel-columns'), 10);
            return isFinite(value) && value > 0 ? value : 1;
        }

        function lastIndex() {
            return Math.max(0, slides.length - perView);
        }

        // Distance from the first slide's leading edge to slide `i`'s.
        function offsetFor(i) {
            var first = slides[0].getBoundingClientRect();
            var target = slides[i].getBoundingClientRect();
            return isRtl() ? first.right - target.right : target.left - first.left;
        }

        function applyTransform(dragDelta) {
            var distance = offsetFor(index);
            var base = isRtl() ? distance : -distance;
            track.style.transform = 'translateX(' + (base + (dragDelta || 0)) + 'px)';
        }

        function update() {
            index = Math.max(0, Math.min(lastIndex(), index));
            applyTransform(0);

            var focused = document.activeElement;
            var focusLost = false;

            // Slides outside the view are inert, so focus never lands in
            // one and screen readers skip them.
            slides.forEach(function (slide, slideIndex) {
                var inView = slideIndex >= index && slideIndex < index + perView;
                slide.classList.toggle('is-inactive', !inView);
                if (inView) {
                    slide.removeAttribute('inert');
                    slide.removeAttribute('aria-hidden');
                } else {
                    if (focused && slide.contains(focused)) {
                        focusLost = true;
                    }
                    slide.setAttribute('inert', '');
                    slide.setAttribute('aria-hidden', 'true');
                }
            });

            if (focusLost) {
                slides[index].setAttribute('tabindex', '-1');
                slides[index].focus({ preventScroll: true });
            }

            var last = lastIndex();
            if (prev) {
                prev.setAttribute('aria-disabled', !loop && index === 0 ? 'true' : 'false');
            }
            if (next) {
                next.setAttribute('aria-disabled', !loop && index === last ? 'true' : 'false');
            }

            dots.forEach(function (dot, dotIndex) {
                var active = dotIndex === index;
                dot.classList.toggle('is-active', active);
                if (active) {
                    dot.setAttribute('aria-current', 'true');
                } else {
                    dot.removeAttribute('aria-current');
                }
            });
        }

        function syncAutoplay(restart) {
            var shouldPlay = canAutoplay() && !userPaused && !hoverPaused && !document.hidden && lastIndex() > 0;

            if (timer !== null && (!shouldPlay || restart)) {
                window.clearInterval(timer);
                timer = null;
            }

            if (shouldPlay && timer === null) {
                timer = window.setInterval(function () {
                    goTo(index >= lastIndex() ? 0 : index + 1);
                }, intervalSeconds * 1000);
            }

            // A rotating carousel doesn't announce each slide.
            track.setAttribute('aria-live', timer !== null ? 'off' : 'polite');
        }

        function goTo(target) {
            var last = lastIndex();
            if (loop) {
                if (target > last) {
                    target = 0;
                } else if (target < 0) {
                    target = last;
                }
            }
            index = Math.max(0, Math.min(last, target));
            update();
        }

        function navigate(target) {
            goTo(target);
            syncAutoplay(true);
        }

        function buildDots() {
            if (!dotsHost) {
                return;
            }

            var count = lastIndex() + 1;

            if (dots.length === count) {
                return;
            }

            var template = dotsHost.getAttribute('data-dot-label') || 'Go to slide :index';
            dotsHost.textContent = '';
            dots = [];

            for (var i = 0; i < count; i += 1) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = CAROUSEL_CLASS + '__dot';
                dot.setAttribute('aria-label', template.replace(':index', String(i + 1)));
                dot.addEventListener('click', navigate.bind(null, i));
                dotsHost.appendChild(dot);
                dots.push(dot);
            }

            dotsHost.hidden = count < 2;
        }

        function refresh() {
            var shown = allSlides.filter(function (slide) {
                return window.getComputedStyle(slide).display !== 'none';
            });
            slides = shown.length > 0 ? shown : allSlides;
            perView = readPerView();
            buildDots();
            update();
            syncAutoplay(false);
        }

        if (prev) {
            prev.addEventListener('click', function () {
                if (prev.getAttribute('aria-disabled') !== 'true') {
                    navigate(index - 1);
                }
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                if (next.getAttribute('aria-disabled') !== 'true') {
                    navigate(index + 1);
                }
            });
        }

        root.addEventListener('keydown', function (event) {
            var target = event.target;

            if (event.defaultPrevented || !target.closest || target.closest('[data-ap-carousel]') !== root) {
                return;
            }

            if (target.closest(CAROUSEL_SKIP_KEYS_IN)) {
                return;
            }

            var forward = isRtl() ? 'ArrowLeft' : 'ArrowRight';
            var backward = isRtl() ? 'ArrowRight' : 'ArrowLeft';
            var destination = null;

            if (event.key === forward) {
                destination = index + 1;
            } else if (event.key === backward) {
                destination = index - 1;
            } else if (event.key === 'Home') {
                destination = 0;
            } else if (event.key === 'End') {
                destination = lastIndex();
            }

            if (destination === null) {
                return;
            }

            event.preventDefault();
            navigate(destination);

            if (target.classList.contains(CAROUSEL_CLASS + '__dot') && dots[index]) {
                dots[index].focus({ preventScroll: true });
            }
        });

        // Focus would otherwise scroll the clipped viewport out of line
        // with the transform.
        if (viewport) {
            viewport.addEventListener('scroll', function () {
                viewport.scrollLeft = 0;
                viewport.scrollTop = 0;
            });
        }

        // Swipe / drag. Vertical gestures stay page scrolls
        // (`touch-action: pan-y` on the track).
        var pointerId = null;
        var dragStartX = 0;
        var dragStartY = 0;
        var dragDelta = 0;
        var dragging = false;
        var suppressClick = false;

        track.addEventListener('pointerdown', function (event) {
            if (
                event.button !== 0 ||
                lastIndex() === 0 ||
                event.target.closest('[data-ap-carousel]') !== root ||
                event.target.closest(CAROUSEL_SKIP_KEYS_IN)
            ) {
                return;
            }
            pointerId = event.pointerId;
            dragStartX = event.clientX;
            dragStartY = event.clientY;
            dragDelta = 0;
            dragging = false;
            suppressClick = false;
        });

        track.addEventListener('pointermove', function (event) {
            if (event.pointerId !== pointerId) {
                return;
            }

            var dx = event.clientX - dragStartX;
            var dy = event.clientY - dragStartY;

            if (!dragging) {
                if (Math.abs(dy) > 10 && Math.abs(dy) > Math.abs(dx)) {
                    pointerId = null;
                    return;
                }
                if (Math.abs(dx) < 6) {
                    return;
                }
                dragging = true;
                suppressClick = true;
                root.classList.add('is-dragging');
                try {
                    track.setPointerCapture(event.pointerId);
                } catch (error) {
                    // Capture is an enhancement; the drag still works.
                }
            }

            dragDelta = dx;
            applyTransform(dragDelta);
        });

        function endDrag(event) {
            if (event.pointerId !== pointerId) {
                return;
            }

            pointerId = null;

            if (!dragging) {
                return;
            }

            dragging = false;
            root.classList.remove('is-dragging');

            var width = slides[0].getBoundingClientRect().width || 1;
            var moved = (isRtl() ? dragDelta : -dragDelta) / width;
            var steps = 0;

            if (Math.abs(moved) >= CAROUSEL_DRAG_THRESHOLD) {
                steps = moved > 0 ? Math.max(1, Math.round(moved)) : Math.min(-1, Math.round(moved));
            }

            dragDelta = 0;
            navigate(index + steps);

            // A touch drag fires no click, so drop the guard once the
            // drag's own click (if any) has been dispatched.
            window.setTimeout(function () {
                suppressClick = false;
            }, 0);
        }

        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);

        // A drag that ends over a link must not follow it.
        track.addEventListener(
            'click',
            function (event) {
                if (suppressClick) {
                    event.preventDefault();
                    event.stopPropagation();
                    suppressClick = false;
                }
            },
            true
        );

        track.addEventListener('dragstart', function (event) {
            event.preventDefault();
        });

        if (toggle) {
            if (!hasAutoplay) {
                toggle.parentNode.removeChild(toggle);
                toggle = null;
            } else {
                // No pause button while reduced motion keeps it still.
                toggle.hidden = !canAutoplay();
                toggle.addEventListener('click', function () {
                    userPaused = !userPaused;
                    toggle.textContent = toggle.getAttribute(userPaused ? 'data-label-play' : 'data-label-pause') || toggle.textContent;
                    syncAutoplay(false);
                });
            }
        }

        if (hasAutoplay) {
            if (motionQuery) {
                var onMotionChange = function () {
                    if (toggle) {
                        toggle.hidden = !canAutoplay();
                    }
                    syncAutoplay(false);
                };

                if (typeof motionQuery.addEventListener === 'function') {
                    motionQuery.addEventListener('change', onMotionChange);
                } else if (typeof motionQuery.addListener === 'function') {
                    motionQuery.addListener(onMotionChange);
                }
            }

            // Mouse only: a tap fires `mouseenter` with no matching leave,
            // which would stall autoplay on touch screens.
            root.addEventListener('pointerenter', function (event) {
                if (event.pointerType === 'mouse') {
                    hoverPaused = true;
                    syncAutoplay(false);
                }
            });
            root.addEventListener('pointerleave', function (event) {
                if (event.pointerType === 'mouse') {
                    hoverPaused = false;
                    syncAutoplay(false);
                }
            });
            // Keyboard focus stops the rotation until the visitor starts
            // it again with the button (WAI-ARIA APG carousel pattern).
            root.addEventListener('focusin', function (event) {
                if (event.target === toggle || userPaused) {
                    return;
                }
                userPaused = true;
                if (toggle) {
                    toggle.textContent = toggle.getAttribute('data-label-play') || toggle.textContent;
                }
                syncAutoplay(false);
            });
            document.addEventListener('visibilitychange', function () {
                syncAutoplay(false);
            });
        }

        // With no arrows or dots, the viewport takes focus so the arrow
        // keys still reach the carousel; the slides outside the view are
        // inert, so nothing else in them can.
        if (viewport && !prev && !next && !dotsHost) {
            viewport.setAttribute('tabindex', '0');
        }

        track.scrollLeft = 0;
        root.classList.add('is-ready');
        if (controls) {
            controls.hidden = false;
        }

        refresh();

        // The observer catches container-size changes; the window listener
        // catches a breakpoint crossing that leaves the carousel's own size
        // unchanged (a fixed-width carousel).
        if (typeof window.ResizeObserver === 'function') {
            new window.ResizeObserver(refresh).observe(root);
        }
        window.addEventListener('resize', refresh);
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

        var carousels = document.querySelectorAll('[data-ap-carousel]');
        for (var c = 0; c < carousels.length; c += 1) {
            bindCarousel(carousels[c]);
        }

        var copyLinks = document.querySelectorAll('a[data-ap-copy-link]');
        for (var k = 0; k < copyLinks.length; k += 1) {
            bindCopyLink(copyLinks[k]);
        }
    }

    ready(init);

    window.addEventListener('ap:rebind', init);
})();
