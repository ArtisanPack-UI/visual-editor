# Reviews Block

**Status:** v1.9 · issue #763
**Block name:** `artisanpack/reviews`

The Reviews block renders review cards from payloads supplied by any host / package / plugin / theme through the `ap.visualEditor.reviews.collectReviews` filter. The block ships zero reviews of its own — every contributor participates by hooking the filter.

The block deliberately emits **no** `Review` or `AggregateRating` JSON-LD. Self-serving review markup on the reviewed entity is ineligible for Google's review-snippet rich result.

---

## Attributes

| Attribute     | Type                                     | Notes |
|---------------|------------------------------------------|-------|
| `layout`      | `'grid' \| 'list'` (default `'grid'`)    | |
| `columns`     | `number` (1–4, default `3`)              | Applies to grid layout. |
| `limit`       | `number` (1–24, default `3`)             | Maximum cards to render. |
| `source`      | `string`                                 | Optional badge filter — restrict cards to reviews whose `source` field matches (case-insensitive). |
| `showStars`   | `boolean` (default `true`)               | |
| `showSource`  | `boolean` (default `true`)               | |
| `showDate`    | `boolean` (default `true`)               | |

## Contributing reviews

Register a callback on the `ap.visualEditor.reviews.collectReviews` filter to supply reviews. The callback receives an array of already-supplied reviews plus the block's validated attributes, and should return the merged array.

```php
addFilter(
    ArtisanPackUI\VisualEditor\Blocks\Core\ReviewsBlock::FILTER_COLLECT_REVIEWS,
    static function ( array $reviews, array $attrs ): array {
        return array_merge( $reviews, [
            [
                'reviewer' => 'Jane Doe',
                'quote'    => 'A wonderful experience.',
                'rating'   => 5,
                'source'   => 'Google',
                'url'      => 'https://www.google.com/maps/reviews/…',
                'date'     => '2026-08-10',
            ],
        ] );
    }
);
```

### Payload shape

Each entry is normalized to:

| Key          | Type   | Notes |
|--------------|--------|-------|
| `reviewer`   | string | Required. |
| `quote`      | string | Required. |
| `rating`     | int    | Optional, 1..5; clamped. |
| `source`     | string | Optional; badge label. |
| `url`        | string | Optional; scheme-allowlisted (`http`, `https`, `mailto`). |
| `date`       | string | Optional; ISO 8601 or human-readable. |
| `avatar_url` | string | Optional; scheme-allowlisted (`http`, `https`, `mailto`). |

Entries missing both `reviewer` and `quote` are dropped.

## Performance

`collectReviews` is memoised per block instance for the request (keyed on the `source` + `limit` attributes) so a page that references the Reviews block more than once — Query loops, synced patterns, the `page/location` starter pattern — only invokes each host resolver once per unique-attribute set.

Hosts fanning out to external review APIs (Google Places, Yelp, Trustpilot) should still cache their fetches at a longer TTL inside the filter callback.
