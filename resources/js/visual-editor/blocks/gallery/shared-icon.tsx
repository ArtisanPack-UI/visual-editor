/**
 * Gallery — shared icon used by the edit-time `MediaPlaceholder`.
 *
 * Ported from `@wordpress/block-library/src/gallery/shared-icon.js`
 * (v9.43.0).
 */

import type { ReactElement } from 'react';
import { BlockIcon } from '@wordpress/block-editor';
import { gallery as icon } from '@wordpress/icons';

export const sharedIcon: ReactElement = <BlockIcon icon={icon} />;
