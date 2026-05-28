/**
 * Cover — placeholder.
 *
 * Ported from `@wordpress/block-library/src/cover/edit/cover-placeholder.js`
 * (v9.43.0). Uses the inline CoverInserterIcon so the editor canvas does
 * not have to load `@wordpress/icons` for the cover SVG.
 */

import type { ReactElement, ReactNode } from 'react';
import { BlockIcon, MediaPlaceholder } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { createBlobURL } from '@wordpress/blob';

import { ALLOWED_MEDIA_TYPES } from '../shared';
import CoverInserterIcon from '../inserter-icon';

interface CoverPlaceholderProps {
    disableMediaButtons?: boolean;
    children?: ReactNode;
    onSelectMedia: (media: { url: string }) => void;
    onError?: (message: string) => void;
    style?: React.CSSProperties;
    toggleUseFeaturedImage?: () => void;
}

export default function CoverPlaceholder({
    disableMediaButtons = false,
    children,
    onSelectMedia,
    onError,
    style,
    toggleUseFeaturedImage,
}: CoverPlaceholderProps): ReactElement {
    const onFilesPreUpload = (files: File[]): void => {
        if (files.length === 1) {
            onSelectMedia({ url: createBlobURL(files[0]) });
        }
    };

    return (
        <MediaPlaceholder
            icon={<BlockIcon icon={<CoverInserterIcon />} />}
            labels={{
                title: __('Cover'),
            }}
            onSelect={onSelectMedia}
            allowedTypes={ALLOWED_MEDIA_TYPES as unknown as string[]}
            disableMediaButtons={disableMediaButtons}
            onToggleFeaturedImage={toggleUseFeaturedImage}
            onFilesPreUpload={onFilesPreUpload}
            onError={onError}
            style={style}
        >
            {children}
        </MediaPlaceholder>
    );
}
