/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Users need blocks for site search functionality and displaying user avatars/profiles throughout the site.

## Proposed Solution

**What would you like to happen?**
Implement search and user display blocks:

### Search Block

```php
'search' => [
    'schema' => [
        'placeholder' => ['type' => 'string', 'default' => 'Search...'],
        'button_text' => ['type' => 'string', 'default' => 'Search'],
        'button_position' => ['type' => 'string', 'enum' => ['inside', 'outside', 'none'], 'default' => 'inside'],
        'show_icon' => ['type' => 'boolean', 'default' => true],
        'width' => ['type' => 'string', 'enum' => ['auto', 'full'], 'default' => 'auto'],
        'content_types' => ['type' => 'array', 'default' => ['all']],
    ],
    'supports' => ['spacing', 'colors', 'typography'],
]
```

Features:
- Search input field with icon
- Button inside, outside, or none (submit on enter)
- Placeholder text customization
- Button text customization
- Auto or full width
- Filter by content types
- Submit to search results page

Implementation:
```blade
<form action="{{ route('search') }}" method="GET" class="search-block">
    @if($show_icon && $button_position === 'inside')
        <i class="search-icon"></i>
    @endif

    <input
        type="search"
        name="q"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
    />

    @if($content_types !== ['all'])
        @foreach($content_types as $type)
            <input type="hidden" name="types[]" value="{{ $type }}" />
        @endforeach
    @endif

    @if($button_position !== 'none')
        <button type="submit">
            {{ $button_text }}
        </button>
    @endif
</form>
```

Search Results Page:
- Integrates with query-loop block
- Uses query-title for "Search Results for: {query}"
- Uses query-total for result count
- Uses query-no-results for empty state

### Avatar Block

```php
'avatar' => [
    'schema' => [
        'user_id' => ['type' => 'integer', 'nullable' => true],
        'size' => ['type' => 'string', 'enum' => ['small', 'medium', 'large', 'xlarge'], 'default' => 'medium'],
        'shape' => ['type' => 'string', 'enum' => ['circle', 'square', 'rounded'], 'default' => 'circle'],
        'show_name' => ['type' => 'boolean', 'default' => false],
        'link_to_profile' => ['type' => 'boolean', 'default' => false],
        'fallback' => ['type' => 'string', 'enum' => ['initials', 'icon', 'image'], 'default' => 'initials'],
        'fallback_image' => ['type' => 'integer', 'nullable' => true],
    ],
    'supports' => ['spacing', 'border'],
]
```

Features:
- Display user avatar/profile picture
- Multiple size options (32px, 64px, 128px, 256px)
- Shape variants (circle, square, rounded square)
- Optional user name display
- Link to user profile page
- Fallback options:
  - User initials (AA)
  - Generic icon
  - Custom fallback image
- Context-aware (shows current user if no ID specified)

Size Presets:
```php
'small' => 32,   // Comments, inline mentions
'medium' => 64,  // Author bylines
'large' => 128,  // Profile pages, cards
'xlarge' => 256, // Full profile headers
```

Context Usage:
```blade
<!-- In comment template -->
<x-artisanpack-avatar :user-id="$comment->user_id" size="small" />

<!-- In author bio -->
<x-artisanpack-avatar :user-id="$author->id" size="large" show-name />

<!-- Current user (no ID needed) -->
<x-artisanpack-avatar size="medium" link-to-profile />
```

Integration with Media Library:
- User profile pictures stored in media library
- Avatar block queries user profile picture
- Falls back to gravatar if configured
- Falls back to initials/icon if no image

## Alternatives Considered

- Third-party search service (rejected: should work out of box)
- Search widget only in header (rejected: users want flexibility)
- Gravatar-only avatars (rejected: need local media library support)
- CSS-only avatar fallbacks (rejected: need proper initials generation)

## Use Cases

1. User adds search form to header
2. User adds search to sidebar
3. User adds search to 404 page
4. User displays author avatar in post byline
5. User creates team member grid with avatars
6. User shows current user avatar in profile dropdown
7. User displays comment author avatars

## Acceptance Criteria

- [ ] Search block renders form with input and button
- [ ] Search block submits to search results page
- [ ] Search block button position options work
- [ ] Search block can filter by content types
- [ ] Avatar block displays user profile picture
- [ ] Avatar size options render correctly
- [ ] Avatar shape variants work (circle, square, rounded)
- [ ] Avatar shows user initials as fallback
- [ ] Avatar can show user name
- [ ] Avatar links to profile when enabled
- [ ] Avatar works in context (current user, specific user)
- [ ] Avatar integrates with media library

---

**Related Issues:**
- Depends on: Block Registry, artisanpack-ui/media-library
- Related: Query Loop (for search results), User Profile System
