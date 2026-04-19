/**
 * Temporary MediaUpload slot-fill stub.
 *
 * `@wordpress/block-editor`'s `MediaUpload` component is a `withFilters`
 * placeholder — upstream WordPress registers the real modal via the
 * `editor.MediaUpload` filter. Without a registered replacement, clicking
 * "Media Library" on core/image's placeholder silently does nothing.
 *
 * This stub gives the user feedback that the feature is wired but not yet
 * implemented. The real implementation arrives in M4 when the media library
 * from `artisanpack-ui/media-library` is bridged into the editor.
 *
 * Tracked by issue #312 (M2 of the Gutenberg adoption, umbrella #309).
 */

import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import {
    Children,
    cloneElement,
    isValidElement,
    type ReactElement,
    type ReactNode,
} from 'react';

import { TEXT_DOMAIN } from './i18n';

interface MediaUploadRenderArgs {
    open: () => void;
}

interface MediaUploadProps {
    render?: (args: MediaUploadRenderArgs) => ReactElement | null;
    children?: ReactNode;
}

const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/media-upload-stub';

function openMediaLibraryStub(): void {
    // eslint-disable-next-line no-alert -- intentional user-facing stub feedback until M4.
    window.alert(
        __(
            'The media library picker is a stub in M2. Integration with artisanpack-ui/media-library lands in M4.',
            TEXT_DOMAIN
        )
    );
}

function MediaUploadStub(props: MediaUploadProps): ReactElement | null {
    const { render, children } = props;

    if (typeof render === 'function') {
        return render({ open: openMediaLibraryStub });
    }

    // Some call sites pass a single button-like child expecting `onClick` to
    // be wired to `open`. Clone it so clicking routes to the stub handler.
    const onlyChild = Children.toArray(children).find(isValidElement);

    if (onlyChild) {
        return cloneElement(onlyChild as ReactElement, {
            onClick: openMediaLibraryStub,
        });
    }

    return null;
}

let registered = false;

export function registerMediaUploadStub(): void {
    if (registered) {
        return;
    }

    addFilter(
        'editor.MediaUpload',
        FILTER_NAMESPACE,
        () => MediaUploadStub
    );
    registered = true;
}

/**
 * Stub `mediaUpload` function for `BlockEditorProvider`'s `settings` prop.
 *
 * `MediaUploadCheck` (in `@wordpress/block-editor`) reads
 * `settings.mediaUpload` via `useSelect` on the block-editor store and hides
 * the "Media Library" button on placeholders like `core/image` when it's
 * falsy. A truthy function is enough to gate the button in — our
 * `editor.MediaUpload` filter above then intercepts the actual click.
 *
 * The real upload pipeline lands in M4 when `artisanpack-ui/media-library`
 * is bridged into the editor.
 */
export function mediaUploadStub(): void {
    // eslint-disable-next-line no-alert -- intentional user-facing stub feedback until M4.
    window.alert(
        __(
            'File upload is a stub in M2. Integration with artisanpack-ui/media-library lands in M4.',
            TEXT_DOMAIN
        )
    );
}
