/**
 * Image and Gallery lightbox overlay (#1046).
 *
 * Vanilla DOM, shared by the Blade, React and Vue renderers. A renderer prints
 * a `.wp-lightbox-container` figure holding the image, a hidden trigger button
 * and a `data-ap-lightbox-labels` attribute. The trigger starts with an inline
 * `display: none`, which the block library's `button` rule can't override, so a
 * page without this script has no button that does nothing. `bindLightbox()`
 * marks the document instead of touching each trigger, and `lightbox.css`
 * reveals them from that class. Clicks are delegated too, so markup that arrives
 * later, or that a Livewire morph resets, needs no binding of its own.
 */

const CONTAINER_SELECTOR = '.wp-lightbox-container';
const TRIGGER_SELECTOR = ':scope > .lightbox-trigger';
const IMAGE_SELECTOR = ':scope > img, :scope > picture > img';
const CAPTION_SELECTOR = ':scope > figcaption';
const GALLERY_SELECTOR = '.wp-block-gallery';
const FOCUSABLE_SELECTOR = 'button:not([hidden]), a[href]';
const CLOSE_ANIMATION_MS = 250;
const READY_CLASS = 'ap-lightbox-ready';

const DEFAULT_LABELS = {
    dialog: 'Enlarged image',
    close: 'Close',
    previous: 'Previous image',
    next: 'Next image',
    position: 'Image :current of :total',
};

const ICONS = {
    close: 'M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z',
    previous: 'M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z',
    next: 'M10.6 6L9.4 7l4.6 5-4.6 5 1.2 1 5.4-6z',
};

const SVG_NS = 'http://www.w3.org/2000/svg';

/** One overlay per document: `{ root, parts, images, index, opener, … }`. */
const states = new WeakMap();

function iconSvg(doc, path) {
    const svg = doc.createElementNS(SVG_NS, 'svg');
    const shape = doc.createElementNS(SVG_NS, 'path');

    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '24');
    svg.setAttribute('height', '24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    shape.setAttribute('d', path);
    svg.appendChild(shape);

    return svg;
}

function createButton(doc, className) {
    const button = doc.createElement('button');

    button.type = 'button';
    button.className = className;

    return button;
}

function createNavigationButton(doc, direction) {
    const button = createButton(doc, `wp-lightbox-navigation-button-${direction === 'previous' ? 'prev' : 'next'}`);
    const icon = doc.createElement('span');
    const text = doc.createElement('span');

    icon.className = 'wp-lightbox-navigation-icon';
    icon.appendChild(iconSvg(doc, ICONS[direction]));
    text.className = 'wp-lightbox-navigation-text';
    button.append(direction === 'previous' ? icon : text, direction === 'previous' ? text : icon);

    return { button, icon, text };
}

function createOverlay(doc) {
    const root = doc.createElement('div');
    const scrim = doc.createElement('div');
    const close = createButton(doc, 'wp-lightbox-close-button');
    const closeIcon = doc.createElement('span');
    const previous = createNavigationButton(doc, 'previous');
    const next = createNavigationButton(doc, 'next');
    const container = doc.createElement('div');
    const figure = doc.createElement('figure');
    const image = doc.createElement('img');
    const live = doc.createElement('div');

    root.className = 'wp-lightbox-overlay';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.tabIndex = -1;
    scrim.className = 'scrim';
    closeIcon.className = 'wp-lightbox-close-icon';
    closeIcon.appendChild(iconSvg(doc, ICONS.close));
    close.appendChild(closeIcon);
    container.className = 'lightbox-image-container';
    figure.className = 'wp-block-image';
    figure.appendChild(image);
    container.appendChild(figure);
    live.className = 'wp-lightbox-live-region';
    live.setAttribute('aria-live', 'polite');
    root.append(scrim, container, previous.button, next.button, close, live);
    doc.body.appendChild(root);

    const state = {
        root,
        close,
        previous,
        next,
        figure,
        image,
        live,
        images: [],
        index: 0,
        opener: null,
        isOpen: false,
        scrollOverflow: '',
        closeTimer: 0,
        labels: DEFAULT_LABELS,
        inerted: [],
    };

    close.addEventListener('click', () => closeOverlay(state));
    previous.button.addEventListener('click', () => step(state, -1));
    next.button.addEventListener('click', () => step(state, 1));
    root.addEventListener('keydown', (event) => handleKeydown(state, event));
    root.addEventListener('click', (event) => {
        if (event.target.closest('button') === null) {
            closeOverlay(state);
        }
    });

    return state;
}

function overlayFor(doc) {
    const existing = states.get(doc);

    if (existing !== undefined && existing.root.isConnected) {
        return existing;
    }

    // Client-side navigation replaced the body while it was open.
    if (existing?.isOpen) {
        doc.documentElement.style.overflow = existing.scrollOverflow;
    }

    const state = createOverlay(doc);

    states.set(doc, state);

    return state;
}

function labelsFor(container) {
    try {
        return { ...DEFAULT_LABELS, ...JSON.parse(container.dataset.apLightboxLabels ?? '{}') };
    } catch {
        return DEFAULT_LABELS;
    }
}

function applyLabels(state, container) {
    const labels = labelsFor(container);
    const gallery = container.closest(GALLERY_SELECTOR);
    const type = gallery?.dataset.apLightboxNavigation ?? 'icon';

    state.labels = labels;
    state.root.setAttribute('aria-label', labels.dialog);
    state.close.setAttribute('aria-label', labels.close);

    for (const [part, label] of [
        [state.previous, labels.previous],
        [state.next, labels.next],
    ]) {
        part.button.setAttribute('aria-label', label);
        part.text.textContent = label;
        part.icon.hidden = type === 'text';
        part.text.hidden = type === 'icon';
    }
}

/** Put one image in the overlay. `srcset` keeps it sharp at full size. */
function showImage(state, index) {
    const container = state.images[index];
    const source = container.querySelector(IMAGE_SELECTOR);
    const caption = container.querySelector(CAPTION_SELECTOR);

    state.index = index;
    state.image.src = source.currentSrc || source.src;
    state.image.alt = source.alt;

    if (source.srcset === '') {
        state.image.removeAttribute('srcset');
        state.image.removeAttribute('sizes');
    } else {
        state.image.srcset = source.srcset;
        state.image.sizes = '100vw';
    }

    state.figure.querySelector('figcaption')?.remove();

    if (caption !== null) {
        state.figure.appendChild(caption.cloneNode(true));
    }
}

function step(state, offset) {
    const { length } = state.images;

    if (length < 2) {
        return;
    }

    showImage(state, (state.index + offset + length) % length);

    // The position changes on every step, so the region always has something
    // new to say, even for an empty or repeated alt text.
    const position = String(state.labels.position ?? DEFAULT_LABELS.position)
        .replace(':current', String(state.index + 1))
        .replace(':total', String(length));

    state.live.textContent = state.image.alt === '' ? position : `${position}: ${state.image.alt}`;
}

function openOverlay(container) {
    const state = overlayFor(container.ownerDocument);

    if (state.isOpen) {
        return;
    }

    const gallery = container.closest(GALLERY_SELECTOR);
    const images =
        gallery === null
            ? [container]
            : Array.from(gallery.querySelectorAll(CONTAINER_SELECTOR)).filter(
                  (candidate) => candidate.querySelector(IMAGE_SELECTOR) !== null
              );
    const root = state.root;
    const scroller = root.ownerDocument.documentElement;

    clearTimeout(state.closeTimer);
    state.images = images;
    state.opener = container.querySelector(TRIGGER_SELECTOR);
    state.isOpen = true;
    state.scrollOverflow = scroller.style.overflow;
    scroller.style.overflow = 'hidden';
    // `aria-modal` alone doesn't keep every screen reader's virtual cursor
    // or touch exploration out of the page behind the dialog.
    state.inerted = Array.from(root.ownerDocument.body.children).filter(
        (element) => element !== root && !element.inert
    );
    state.inerted.forEach((element) => {
        element.inert = true;
    });
    applyLabels(state, container);
    state.previous.button.hidden = images.length < 2;
    state.next.button.hidden = images.length < 2;
    showImage(state, images.indexOf(container));
    state.live.textContent = '';
    root.classList.remove('show-closing-animation');
    root.classList.add('active');
    state.close.focus();
}

function closeOverlay(state) {
    if (!state.isOpen) {
        return;
    }

    const { root, opener } = state;

    state.isOpen = false;
    root.ownerDocument.documentElement.style.overflow = state.scrollOverflow;
    root.classList.remove('active');
    root.classList.add('show-closing-animation');
    state.closeTimer = setTimeout(() => {
        root.classList.remove('show-closing-animation');
    }, CLOSE_ANIMATION_MS);
    state.inerted.forEach((element) => {
        element.inert = false;
    });
    state.inerted = [];
    opener?.focus();
}

function trapFocus(state, event) {
    const focusable = Array.from(state.root.querySelectorAll(FOCUSABLE_SELECTOR));
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = state.root.ownerDocument.activeElement;

    if (event.shiftKey && (active === first || active === state.root)) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}

function handleKeydown(state, event) {
    switch (event.key) {
        case 'Escape':
            event.preventDefault();
            closeOverlay(state);
            break;
        case 'ArrowLeft':
            event.preventDefault();
            step(state, -1);
            break;
        case 'ArrowRight':
            event.preventDefault();
            step(state, 1);
            break;
        case 'Tab':
            trapFocus(state, event);
            break;
        default:
    }
}

/** The lightbox a click belongs to: its trigger or its image, nothing else. */
function containerForClick(target) {
    const container = target.closest(CONTAINER_SELECTOR);

    if (container === null) {
        return null;
    }

    const isTrigger = target.closest('.lightbox-trigger')?.parentElement === container;

    return isTrigger || container.querySelector(IMAGE_SELECTOR) === target ? container : null;
}

function handleDocumentClick(event) {
    const { target } = event;
    const container = target instanceof Element ? containerForClick(target) : null;

    if (container !== null && container.querySelector(IMAGE_SELECTOR) !== null) {
        openOverlay(container);
    }
}

const boundDocuments = new WeakSet();

function closeOpenOverlay(doc) {
    const state = states.get(doc);

    if (state !== undefined) {
        closeOverlay(state);
    }
}

/**
 * Reveal the triggers on a document and listen for its clicks. Safe to call
 * repeatedly.
 *
 * @param {Document|Element} root The document, or any element in it.
 */
export function bindLightbox(root) {
    const doc = root.ownerDocument ?? root;

    if (boundDocuments.has(doc)) {
        return;
    }

    boundDocuments.add(doc);
    doc.documentElement.classList.add(READY_CLASS);
    doc.addEventListener('click', handleDocumentClick);
    doc.addEventListener('turbo:before-cache', () => closeOpenOverlay(doc));
    doc.defaultView?.addEventListener('popstate', () => closeOpenOverlay(doc));
}
