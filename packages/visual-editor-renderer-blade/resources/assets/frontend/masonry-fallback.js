/**
 * Masonry fallback bootstrap (#593).
 *
 * Loaded on the public frontend via `<x-ve-blocks-styles />`. Walks the
 * page for wrappers carrying `.is-layout-masonry` or `.ap-grid.ap-grid-
 * layout-masonry` and initialises the shortest-column-first packing
 * routine on each. The routine itself is renderer-agnostic and lives
 * in the editor source at `resources/js/visual-editor/blocks/_shared/
 * masonry/fallback.ts` — this file is a JS port of that logic so the
 * Blade renderer can ship it as a plain script without a build step.
 *
 * Native CSS Grid masonry is detected once and the routine no-ops when
 * native support is present.
 *
 * The script attaches a controller to each container under
 * `el.__apMasonryController` so callers (Livewire hosts, SPA hosts) can
 * call `relayout()` / `destroy()` programmatically.
 */
(function () {
    'use strict';

    var ITEM_CLASS = 'ap-masonry-item';
    var WRAPPER_CLASS = 'ap-masonry-js-fallback';
    // Tailwind-style breakpoint min-widths in ascending order. Mirrors
    // resources/js/visual-editor/responsive/registry.ts.
    var BREAKPOINT_MIN_WIDTHS = [
        ['sm', 640],
        ['md', 768],
        ['lg', 1024],
        ['xl', 1280],
        ['2xl', 1536]
    ];

    function supportsNative() {
        if (typeof window === 'undefined' || typeof window.CSS === 'undefined') {
            return false;
        }
        if (typeof window.__apMasonrySupports === 'boolean') {
            return window.__apMasonrySupports;
        }
        var ok = false;
        try {
            ok = window.CSS.supports('grid-template-rows', 'masonry');
        } catch (err) {
            ok = false;
        }
        window.__apMasonrySupports = ok;
        return ok;
    }

    function clampColumns(value, fallback) {
        if (typeof value !== 'number' || !isFinite(value)) {
            return fallback;
        }
        var truncated = Math.trunc(value);
        if (truncated < 1) return 1;
        if (truncated > 12) return 12;
        return truncated;
    }

    function readColumnsFromAttr(container, fallback) {
        var attr = container.getAttribute('data-ap-cols');
        if (attr === null || attr === '') {
            return fallback;
        }
        var parsed = Number(attr);
        return clampColumns(parsed, fallback);
    }

    // Walk the breakpoint table in ascending min-width order and pick
    // the column count from the widest breakpoint whose min-width the
    // current viewport meets. Falls back to the base `data-ap-cols`
    // (or the given fallback) when no breakpoint matches.
    function readActiveColumnsFromAttrs(container, fallback) {
        var base = readColumnsFromAttr(container, fallback);
        if (typeof window === 'undefined') {
            return base;
        }
        var viewportWidth = window.innerWidth || 0;
        var active = base;
        for (var i = 0; i < BREAKPOINT_MIN_WIDTHS.length; i += 1) {
            var bp = BREAKPOINT_MIN_WIDTHS[i][0];
            var minWidth = BREAKPOINT_MIN_WIDTHS[i][1];
            if (viewportWidth < minWidth) {
                break;
            }
            var attr = container.getAttribute('data-ap-cols-' + bp);
            if (attr === null || attr === '') {
                continue;
            }
            var parsed = Number(attr);
            if (!isFinite(parsed)) {
                continue;
            }
            active = clampColumns(parsed, active);
        }
        return active;
    }

    function readItemSpan(el, maxColumns) {
        var dataAttr = el.getAttribute('data-ap-col-span');
        if (dataAttr !== null) {
            var parsed = Number(dataAttr);
            if (isFinite(parsed)) {
                return Math.min(maxColumns, Math.max(1, Math.trunc(parsed)));
            }
        }
        for (var span = maxColumns; span >= 2; span -= 1) {
            if (el.classList.contains('ap-grid-item-span-' + span + '-base-columns')) {
                return span;
            }
        }
        return 1;
    }

    function readGapPx(container) {
        var computed = window.getComputedStyle(container);
        var raw = computed.rowGap || computed.gap || '0';
        var parsed = parseFloat(raw);
        return isFinite(parsed) ? Math.max(0, parsed) : 0;
    }

    function collectItems(container) {
        var items = [];
        var children = container.children;
        for (var i = 0; i < children.length; i += 1) {
            if (children[i] instanceof HTMLElement) {
                items.push(children[i]);
            }
        }
        return items;
    }

    function snapshotStyle(el) {
        return {
            position: el.style.position,
            top: el.style.top,
            left: el.style.left,
            width: el.style.width,
            boxSizing: el.style.boxSizing,
        };
    }

    function restoreStyle(el, prev) {
        el.style.position = prev.position;
        el.style.top = prev.top;
        el.style.left = prev.left;
        el.style.width = prev.width;
        el.style.boxSizing = prev.boxSizing;
        el.classList.remove(ITEM_CLASS);
    }

    function findSlot(columnHeights, span) {
        if (span >= columnHeights.length) return 0;
        var bestSlot = 0;
        var bestMax = Number.POSITIVE_INFINITY;
        var lastStart = columnHeights.length - span;
        for (var start = 0; start <= lastStart; start += 1) {
            var windowMax = -Infinity;
            for (var i = start; i < start + span; i += 1) {
                if (columnHeights[i] > windowMax) {
                    windowMax = columnHeights[i];
                }
            }
            if (windowMax < bestMax) {
                bestMax = windowMax;
                bestSlot = start;
            }
        }
        return bestSlot;
    }

    function sliceMaxHeight(columnHeights, slot, span) {
        var max = 0;
        for (var i = slot; i < slot + span; i += 1) {
            if (columnHeights[i] > max) {
                max = columnHeights[i];
            }
        }
        return max;
    }

    function initMasonry(container, forceFallback) {
        if (!forceFallback && supportsNative()) {
            return { relayout: function () {}, destroy: function () {} };
        }

        var destroyed = false;
        var placements = [];
        // Forward-declared so relayout() can unobserve removed items
        // from the ResizeObserver. Assigned just before the first
        // relayout() invocation below.
        var ro = null;
        var initialWrapperStyle = {
            position: container.style.position,
            height: container.style.height,
        };
        var classesAdded = [];
        if (!container.classList.contains(WRAPPER_CLASS)) {
            container.classList.add(WRAPPER_CLASS);
            classesAdded.push(WRAPPER_CLASS);
        }

        function trackPlacement(el, span) {
            var placement = { el: el, span: span, prevStyle: snapshotStyle(el) };
            if (!el.classList.contains(ITEM_CLASS)) {
                el.classList.add(ITEM_CLASS);
            }
            return placement;
        }

        function relayout() {
            if (destroyed) return;
            var items = collectItems(container);
            var columns = clampColumns(readActiveColumnsFromAttrs(container, 3), 3);
            var gap = readGapPx(container);

            var liveSet = new Set(items);
            for (var p = 0; p < placements.length; p += 1) {
                if (!liveSet.has(placements[p].el)) {
                    restoreStyle(placements[p].el, placements[p].prevStyle);
                    if (ro) ro.unobserve(placements[p].el);
                }
            }
            var placementByEl = new Map();
            for (var q = 0; q < placements.length; q += 1) {
                placementByEl.set(placements[q].el, placements[q]);
            }
            var next = [];
            for (var i = 0; i < items.length; i += 1) {
                var span = readItemSpan(items[i], columns);
                var existing = placementByEl.get(items[i]);
                if (existing) {
                    existing.span = span;
                    next.push(existing);
                } else {
                    next.push(trackPlacement(items[i], span));
                }
            }
            placements = next;

            var containerWidth = container.clientWidth;
            if (containerWidth <= 0 || items.length === 0) {
                container.style.height = '0px';
                return;
            }

            var totalGap = gap * (columns - 1);
            var columnWidth = (containerWidth - totalGap) / columns;
            var columnHeights = new Array(columns);
            for (var c = 0; c < columns; c += 1) {
                columnHeights[c] = 0;
            }

            for (var k = 0; k < placements.length; k += 1) {
                var place = placements[k];
                var clampedSpan = Math.min(place.span, columns);
                var slot = findSlot(columnHeights, clampedSpan);
                var left = slot * (columnWidth + gap);
                var top = sliceMaxHeight(columnHeights, slot, clampedSpan);
                var width = columnWidth * clampedSpan + gap * (clampedSpan - 1);

                place.el.style.position = 'absolute';
                place.el.style.boxSizing = 'border-box';
                place.el.style.left = left + 'px';
                place.el.style.top = top + 'px';
                place.el.style.width = width + 'px';

                var itemHeight = place.el.offsetHeight;
                var newHeight = top + itemHeight + gap;
                for (var s = slot; s < slot + clampedSpan; s += 1) {
                    columnHeights[s] = newHeight;
                }
            }

            var maxHeight = 0;
            for (var d = 0; d < columnHeights.length; d += 1) {
                if (columnHeights[d] > maxHeight) {
                    maxHeight = columnHeights[d];
                }
            }
            maxHeight = Math.max(0, maxHeight - gap);
            container.style.height = maxHeight + 'px';
        }

        relayout();

        ro = (typeof ResizeObserver !== 'undefined') ? new ResizeObserver(function () { relayout(); }) : null;
        if (ro) ro.observe(container);

        var observedItems = (typeof WeakSet !== 'undefined') ? new WeakSet() : null;
        function observeNewItems() {
            if (!ro || !observedItems) return;
            for (var p = 0; p < placements.length; p += 1) {
                if (!observedItems.has(placements[p].el)) {
                    ro.observe(placements[p].el);
                    observedItems.add(placements[p].el);
                }
            }
        }
        observeNewItems();

        var mo = (typeof MutationObserver !== 'undefined') ? new MutationObserver(function () {
            relayout();
            observeNewItems();
        }) : null;
        if (mo) {
            mo.observe(container, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'data-ap-col-span', 'data-ap-cols'],
            });
        }

        function onLoad(event) {
            if (event.target instanceof HTMLImageElement && container.contains(event.target)) {
                relayout();
            }
        }
        container.addEventListener('load', onLoad, true);

        function onResize() { relayout(); }
        window.addEventListener('resize', onResize);

        // Tame the empty-then-painted race: items can mount empty and
        // stream content in across the first few frames. A short rAF
        // chain catches the paint without waiting on observers.
        if (typeof window.requestAnimationFrame === 'function') {
            var kicks = 0;
            function kick() {
                if (destroyed || kicks > 3) return;
                kicks += 1;
                relayout();
                window.requestAnimationFrame(kick);
            }
            window.requestAnimationFrame(kick);
        }

        return {
            relayout: relayout,
            destroy: function () {
                if (destroyed) return;
                destroyed = true;
                if (ro) ro.disconnect();
                if (mo) mo.disconnect();
                container.removeEventListener('load', onLoad, true);
                window.removeEventListener('resize', onResize);
                for (var p = 0; p < placements.length; p += 1) {
                    restoreStyle(placements[p].el, placements[p].prevStyle);
                }
                placements = [];
                container.style.position = initialWrapperStyle.position;
                container.style.height = initialWrapperStyle.height;
                for (var c = 0; c < classesAdded.length; c += 1) {
                    container.classList.remove(classesAdded[c]);
                }
                // Clear the marker so bootstrap() can reinitialize the
                // container if it is re-introduced later (SPA hydration,
                // Livewire morph, etc.).
                try {
                    delete container.__apMasonryController;
                } catch (err) {
                    container.__apMasonryController = undefined;
                }
            },
        };
    }

    function bootstrap() {
        var selector = '.is-layout-masonry, .ap-grid.ap-grid-layout-masonry';
        var elements = document.querySelectorAll(selector);
        for (var i = 0; i < elements.length; i += 1) {
            var el = elements[i];
            if (el.__apMasonryController) {
                continue;
            }
            el.__apMasonryController = initMasonry(el, false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

    // Expose for SPA / Livewire hosts that mount masonry containers
    // after initial paint.
    window.apMasonry = window.apMasonry || {};
    window.apMasonry.init = initMasonry;
    window.apMasonry.bootstrap = bootstrap;
    window.apMasonry.supportsNative = supportsNative;
})();
