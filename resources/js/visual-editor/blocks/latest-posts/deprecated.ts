/**
 * Latest Posts — deprecations.
 *
 * Ported from `@wordpress/block-library/src/latest-posts/deprecated.js`
 * (v9.43.0). The single v1 entry migrates the legacy string `categories`
 * attribute (a bare term id) to the current array-of-objects shape so
 * older saved markup deserializes cleanly under the fork. `save` returns
 * `null` because the block is server-rendered.
 */

import metadata from './block.json';

const { attributes } = metadata;

interface LegacyLatestPostsAttributes {
    readonly categories?: string;
    readonly [key: string]: unknown;
}

const deprecated = [
    {
        attributes: {
            ...attributes,
            categories: {
                type: 'string',
            },
        },
        supports: {
            align: true,
            html: false,
        },
        migrate: ( oldAttributes: LegacyLatestPostsAttributes ) => {
            // The current schema needs the full category object, not just
            // the id.
            return {
                ...oldAttributes,
                categories: [ { id: Number( oldAttributes.categories ) } ],
            };
        },
        isEligible: ( { categories }: LegacyLatestPostsAttributes ): boolean =>
            categories !== undefined && 'string' === typeof categories,
        save: (): null => null,
    },
];

export default deprecated;
