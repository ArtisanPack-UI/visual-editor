# Business Info Blocks

**Status:** v1.9 · issue #761
**Block names:**
- `artisanpack/business-hours`
- `artisanpack/business-address`
- `artisanpack/business-phone`
- `artisanpack/business-email`

Four dynamic blocks that render a host-supplied business envelope resolved through the `ap.visualEditor.businessInfo` filter.

---

## Supplying the envelope

Hook the `ap.visualEditor.businessInfo` filter to return the business record:

```php
addFilter( 'ap.visualEditor.businessInfo', static function ( array $info ): array {
    return array_merge( $info, [
        'name'        => 'Acme Bakery',
        'address'     => [
            'street'  => '123 Main St',
            'city'    => 'Springfield',
            'region'  => 'IL',
            'postal'  => '62701',
            'country' => 'US',
            'lat'     => 39.7817,
            'lng'     => -89.6501,
        ],
        'phone'       => '+1-555-123-4567',
        'email'       => 'hello@acmebakery.example',
        'hours'       => [
            'monday'    => [ [ '09:00', '17:00' ] ],
            // …
        ],
        'specialHours' => [
            // Optional windowed overrides (holidays, seasonal closures).
        ],
    ] );
} );
```

## Address / map

The `business-address` block composes a map embed URL in this order:

1. A host-supplied URL, if the filter payload provides one.
2. A Google Maps embed if `config('artisanpack.visual-editor.business.google_maps_api_key')` is set.
3. An OpenStreetMap embed around the address `lat`/`lng`.

If none of these can be composed, the `<iframe>` is dropped entirely.

The map iframe is sandboxed and its URL parameters are `rawurlencode`d.

## REST endpoint

`GET /visual-editor/api/business-info` returns the resolved envelope so the editor previews can render live. The endpoint is guarded by the standard `api` + `auth` middleware group. Accepts optional query parameters `showMap`, `mapProvider`, `zoom`, and `specialHoursWindowDays` — anything else is ignored.
