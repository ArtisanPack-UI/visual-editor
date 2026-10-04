/**
 * Heading — block variations.
 *
 * Ported from `@wordpress/block-library/src/heading/variations.js`
 * (v9.43.0). Upstream dropped the level dropdown from the heading's
 * `edit` in favour of one variation per level (h1–h6); the block
 * toolbar's variation switcher is now the only level picker. Without
 * these the fork had no way to change a heading's level.
 */

import { __, sprintf } from '@wordpress/i18n';
import {
    headingLevel1,
    headingLevel2,
    headingLevel3,
    headingLevel4,
    headingLevel5,
    headingLevel6,
} from '@wordpress/icons';

interface HeadingAttributes {
    readonly level?: number;
}

interface Variation {
    name: string;
    title: string;
    description: string;
    icon: unknown;
    attributes: { level: number };
    scope: Array<'block' | 'inserter' | 'transform'>;
    keywords: string[];
    isActive: (blockAttributes: HeadingAttributes) => boolean;
}

const LEVEL_ICONS = [
    headingLevel1,
    headingLevel2,
    headingLevel3,
    headingLevel4,
    headingLevel5,
    headingLevel6,
];

const variations: Variation[] = [1, 2, 3, 4, 5, 6].map((level) => ({
    name: `h${level}`,
    title: sprintf(
        /* translators: %d: heading level e.g: "1", "2", "3" */
        __('Heading %d'),
        level,
    ),
    description: __(
        'Introduce new sections and organize content to help visitors (and search engines) understand the structure of your content.',
    ),
    icon: LEVEL_ICONS[level - 1],
    attributes: { level },
    scope: ['block', 'transform'],
    keywords: [`h${level}`],
    isActive: (blockAttributes) => blockAttributes.level === level,
}));

export default variations;
