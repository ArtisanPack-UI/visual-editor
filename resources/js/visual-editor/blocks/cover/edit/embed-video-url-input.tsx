/**
 * Cover — embed video URL input.
 *
 * Ported from
 * `@wordpress/block-library/src/cover/edit/embed-video-url-input.js`
 * (v9.43.0).
 */

import type { ReactElement } from 'react';
import { useState } from '@wordpress/element';
import {
    // eslint-disable-next-line camelcase
    __experimentalConfirmDialog as ConfirmDialog,
    // eslint-disable-next-line camelcase
    __experimentalVStack as VStack,
    TextControl,
    Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { isValidVideoEmbedUrl } from '../embed-video-utils';

interface EmbedVideoUrlInputProps {
    onSubmit: (url: string) => void;
    onClose: () => void;
    initialUrl?: string;
}

export default function EmbedVideoUrlInput({
    onSubmit,
    onClose,
    initialUrl = '',
}: EmbedVideoUrlInputProps): ReactElement {
    const [url, setUrl] = useState<string>(initialUrl);
    const [error, setError] = useState<string>('');

    const handleConfirm = (): void => {
        if (!url) {
            setError(__('Please enter a URL.'));
            return;
        }

        if (!isValidVideoEmbedUrl(url)) {
            setError(
                __(
                    'This URL is not supported. Please enter a valid video link from a supported provider.'
                )
            );
            return;
        }

        onSubmit(url);
        onClose();
    };

    return (
        <ConfirmDialog
            isOpen
            onConfirm={handleConfirm}
            onCancel={onClose}
            confirmButtonText={__('Add video')}
            size="medium"
        >
            <VStack spacing={4}>
                {error && (
                    <Notice status="error" isDismissible={false}>
                        {error}
                    </Notice>
                )}
                <TextControl
                    type="url"
                    __next40pxDefaultSize
                    label={__('Video URL')}
                    value={url}
                    onChange={(value: string) => {
                        setUrl(value);
                        setError('');
                    }}
                    placeholder={__(
                        'Enter YouTube, Vimeo, or other video URL'
                    )}
                    help={__(
                        'Add a background video to the cover block that will autoplay in a loop.'
                    )}
                />
            </VStack>
        </ConfirmDialog>
    );
}
