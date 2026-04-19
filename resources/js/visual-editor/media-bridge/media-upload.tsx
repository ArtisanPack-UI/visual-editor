/**
 * Gutenberg `MediaUpload` slot-fill replacement.
 *
 * Core blocks (`core/image`, `core/gallery`, `core/video`, `core/audio`,
 * `core/file`, `core/cover`, `core/media-text`) render a `<MediaUpload>`
 * slot and expect the filter-registered component to own picker UI. We
 * replace the stock placeholder with a component that opens the host's
 * registered bridge (typically `MediaModal` from
 * `artisanpack-ui/media-library`) and translates the selection back into
 * Gutenberg's attachment contract.
 */

import {
    Children,
    cloneElement,
    isValidElement,
    useState,
    type ReactElement,
    type ReactNode,
} from 'react';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { allowedTypesToBridgeTypes, mediaToGutenberg } from './adapter';
import { getMediaBridge, setMediaUploadComponent } from './state';
import type {
    BridgeMedia,
    GutenbergMedia,
    GutenbergMediaUploadProps,
} from './types';

const BRIDGE_CONTEXT = 'artisanpack-ui/visual-editor/media-upload';

export function MediaUploadBridge(
    props: GutenbergMediaUploadProps
): ReactElement | null {
    const [open, setOpen] = useState(false);
    const Bridge = getMediaBridge();

    const { render, children } = props;
    const trigger = renderTrigger({
        render,
        children,
        open: () => {
            if (Bridge === null) {
                notifyUnconfigured();
                return;
            }
            setOpen(true);
        },
    });

    if (Bridge === null || !open) {
        return <>{trigger}</>;
    }

    const multiple =
        props.multiple === true ||
        props.multiple === 'add' ||
        props.gallery === true;

    const handleSelect = (media: BridgeMedia[]): void => {
        if (typeof props.onSelect !== 'function') {
            return;
        }

        const converted = media.map(mediaToGutenberg);

        if (multiple) {
            props.onSelect(converted);
        } else {
            const first = converted[0];
            if (first !== undefined) {
                props.onSelect(first);
            }
        }
    };

    return (
        <>
            {trigger}
            <Bridge
                open={open}
                onClose={() => setOpen(false)}
                onSelect={(media: BridgeMedia[]) => {
                    handleSelect(media);
                    // `onSelect` resolves the picker — close defensively
                    // in case the bridge does not auto-close (for example
                    // when the host has customised `onSelect`).
                    setOpen(false);
                }}
                multiSelect={multiple}
                allowedTypes={allowedTypesToBridgeTypes(props.allowedTypes)}
                context={BRIDGE_CONTEXT}
                title={props.title}
            />
        </>
    );
}

setMediaUploadComponent(MediaUploadBridge);

interface TriggerArgs {
    render?: GutenbergMediaUploadProps['render'];
    children?: ReactNode;
    open: () => void;
}

function renderTrigger(args: TriggerArgs): ReactNode {
    const { render, children, open } = args;

    if (typeof render === 'function') {
        return render({ open });
    }

    const onlyChild = Children.toArray(children).find(isValidElement);
    if (!onlyChild) {
        return null;
    }

    const element = onlyChild as ReactElement<{
        onClick?: (event: unknown) => void;
    }>;

    const existingOnClick = element.props.onClick;

    return cloneElement(element, {
        onClick: (event: unknown) => {
            existingOnClick?.(event);
            open();
        },
    });
}

/**
 * Inform the editor operator that no bridge has been registered. Keeps
 * the visible behaviour of the M2 stub so unconfigured integrations fail
 * loudly rather than silently dropping clicks.
 */
function notifyUnconfigured(): void {
    // eslint-disable-next-line no-alert -- intentional operator-facing fallback.
    window.alert(
        __(
            'Media picker is not configured. Call registerMediaBridge() with MediaModal and uploadMedia from artisanpack-ui/media-library.',
            TEXT_DOMAIN
        )
    );
}

/**
 * Re-export the Gutenberg-shape Media type so callers can annotate custom
 * `onSelect` handlers without importing directly from the types module.
 */
export type { GutenbergMedia };
