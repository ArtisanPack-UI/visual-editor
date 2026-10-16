/**
 * Cover — variations.
 *
 * Ported from `@wordpress/block-library/src/cover/variations.js` (v9.43.0).
 */

import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import { cover } from '@wordpress/icons';

const variations = [
    {
        name: 'cover',
        title: __('Cover', TEXT_DOMAIN),
        description: __('Add an image or video with a text overlay.', TEXT_DOMAIN),
        attributes: { layout: { type: 'constrained' } },
        isDefault: true,
        icon: cover,
    },
];

export default variations;
