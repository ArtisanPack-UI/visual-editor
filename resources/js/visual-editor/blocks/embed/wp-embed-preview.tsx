/**
 * Embed — WordPress embed preview.
 *
 * Ported from `@wordpress/block-library/src/embed/wp-embed-preview.js`
 * (v9.43.0).
 */

import type { ReactElement } from 'react';
import { useMergeRefs, useFocusableIframe } from '@wordpress/compose';
import { useRef, useEffect, useMemo } from '@wordpress/element';

const attributeMap: Record<string, string> = {
    class: 'className',
    frameborder: 'frameBorder',
    marginheight: 'marginHeight',
    marginwidth: 'marginWidth',
};

interface WpEmbedPreviewProps {
    readonly html: string;
}

interface IframeProps {
    [key: string]: string;
}

interface ResizeMessage {
    data?: {
        secret?: string;
        message?: string;
        value?: string;
    };
}

export default function WpEmbedPreview({
    html,
}: WpEmbedPreviewProps): ReactElement {
    const ref = useRef<HTMLIFrameElement | null>(null);
    const props = useMemo<IframeProps>(() => {
        const doc = new window.DOMParser().parseFromString(html, 'text/html');
        const iframe = doc.querySelector('iframe');
        const iframeProps: IframeProps = {};

        if (!iframe) {
            return iframeProps;
        }

        Array.from(iframe.attributes).forEach(({ name, value }) => {
            if (name === 'style') {
                return;
            }
            iframeProps[attributeMap[name] || name] = value;
        });

        return iframeProps;
    }, [html]);

    useEffect(() => {
        if (!ref.current) {
            return;
        }
        const { ownerDocument } = ref.current;
        const defaultView = ownerDocument.defaultView;
        if (!defaultView) {
            return;
        }

        function resizeWPembeds(event: ResizeMessage): void {
            const { secret, message, value } = event.data ?? {};
            if (
                !ref.current ||
                message !== 'height' ||
                secret !== props['data-secret']
            ) {
                return;
            }
            ref.current.height = String(value ?? '');
        }

        defaultView.addEventListener(
            'message',
            resizeWPembeds as unknown as EventListener
        );
        return () => {
            defaultView.removeEventListener(
                'message',
                resizeWPembeds as unknown as EventListener
            );
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const focusableRef = useFocusableIframe() as unknown as React.Ref<HTMLIFrameElement>;
    const mergedRef = useMergeRefs([ref, focusableRef]);

    return (
        <div className="wp-block-embed__wrapper">
            <iframe
                ref={mergedRef}
                title={props.title}
                {...props}
            />
        </div>
    );
}
