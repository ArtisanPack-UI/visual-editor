/**
 * Search — variations.
 *
 * Ported from `@wordpress/block-library/src/search/variations.js`
 * (v9.43.0). The single default variation seeds the button text and label
 * with the localized "Search" string on insertion.
 */

import { __ } from '@wordpress/i18n';

const variations = [
    {
        name: 'default',
        isDefault: true,
        attributes: { buttonText: __( 'Search' ), label: __( 'Search' ) },
    },
];

export default variations;
