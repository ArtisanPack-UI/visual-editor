/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Low" ~"Area::Frontend" ~"Phase::3"

## Problem Statement

**Is your feature request related to a problem?**
Content pages need comment system blocks to display and manage user comments without requiring custom code.

## Proposed Solution

**What would you like to happen?**
Implement comment system blocks that provide a complete commenting solution:

### Comments Section Block (Complete System)

```php
'comments-section' => [
    'schema' => [
        'title' => ['type' => 'string', 'default' => 'Comments'],
        'show_title' => ['type' => 'boolean', 'default' => true],
        'show_count' => ['type' => 'boolean', 'default' => true],
        'show_form' => ['type' => 'boolean', 'default' => true],
        'form_position' => ['type' => 'string', 'enum' => ['above', 'below'], 'default' => 'below'],
        'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
        'threaded' => ['type' => 'boolean', 'default' => true],
        'max_depth' => ['type' => 'integer', 'default' => 5],
        'per_page' => ['type' => 'integer', 'default' => 50],
        'require_login' => ['type' => 'boolean', 'default' => false],
        'moderation' => ['type' => 'boolean', 'default' => true],
    ],
    'supports' => ['spacing', 'background', 'border'],
]
```

Features:
- Complete comment system (list + form)
- Title and count display
- Form above or below comments
- Threaded/nested comments
- Pagination
- Login requirement option
- Moderation queue support
- Reply functionality
- Edit/delete for authors
- Admin moderation controls

### Comments List Block (Display Only)

```php
'comments-list' => [
    'schema' => [
        'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
        'threaded' => ['type' => 'boolean', 'default' => true],
        'max_depth' => ['type' => 'integer', 'default' => 5],
        'per_page' => ['type' => 'integer', 'default' => 50],
        'show_avatars' => ['type' => 'boolean', 'default' => true],
        'avatar_size' => ['type' => 'string', 'enum' => ['small', 'medium'], 'default' => 'small'],
        'show_date' => ['type' => 'boolean', 'default' => true],
        'date_format' => ['type' => 'string', 'default' => 'relative'],
        'show_reply' => ['type' => 'boolean', 'default' => true],
    ],
    'supports' => ['spacing', 'typography'],
]
```

Features:
- Display comments only (no form)
- Configurable display options
- Avatar display toggle
- Date formatting (relative, absolute)
- Reply button toggle
- Threaded display
- Pagination

Template Structure:
```blade
<div class="comments-list">
    @foreach($comments as $comment)
        <div class="comment" id="comment-{{ $comment->id }}">
            @if($show_avatars)
                <x-artisanpack-avatar :user-id="$comment->user_id" :size="$avatar_size" />
            @endif

            <div class="comment-meta">
                <strong>{{ $comment->author_name }}</strong>
                @if($show_date)
                    <time>{{ $comment->created_at->diffForHumans() }}</time>
                @endif
            </div>

            <div class="comment-content">
                {!! nl2br(e($comment->content)) !!}
            </div>

            @if($show_reply && $comment->depth < $max_depth)
                <button wire:click="reply({{ $comment->id }})">Reply</button>
            @endif

            @if($comment->replies->isNotEmpty() && $threaded)
                <div class="comment-replies">
                    {{-- Recursive nested comments --}}
                </div>
            @endif
        </div>
    @endforeach

    {{ $comments->links() }}
</div>
```

### Comment Form Block (Submission Only)

```php
'comment-form' => [
    'schema' => [
        'title' => ['type' => 'string', 'default' => 'Leave a Comment'],
        'show_title' => ['type' => 'boolean', 'default' => true],
        'require_name' => ['type' => 'boolean', 'default' => true],
        'require_email' => ['type' => 'boolean', 'default' => true],
        'require_login' => ['type' => 'boolean', 'default' => false],
        'show_url_field' => ['type' => 'boolean', 'default' => false],
        'submit_text' => ['type' => 'string', 'default' => 'Post Comment'],
        'success_message' => ['type' => 'string', 'default' => 'Your comment has been submitted for moderation.'],
    ],
    'supports' => ['spacing', 'background', 'border'],
]
```

Features:
- Standalone comment form
- Field requirements configuration
- Login wall option
- Custom submit button text
- Success message customization
- Validation
- Spam protection (honeypot)

Form Fields:
```blade
<form wire:submit.prevent="submitComment">
    @auth
        <p>Logged in as {{ auth()->user()->name }}</p>
    @else
        @if($require_name)
            <input type="text" wire:model="name" required />
        @endif

        @if($require_email)
            <input type="email" wire:model="email" required />
        @endif

        @if($show_url_field)
            <input type="url" wire:model="url" />
        @endif
    @endauth

    <textarea wire:model="content" required></textarea>

    {{-- Honeypot for spam --}}
    <input type="text" name="website" style="display:none" />

    <button type="submit">{{ $submit_text }}</button>
</form>
```

### Comment Count Block

```php
'comment-count' => [
    'schema' => [
        'format' => ['type' => 'string', 'enum' => ['number', 'short', 'long'], 'default' => 'short'],
        'singular' => ['type' => 'string', 'default' => 'comment'],
        'plural' => ['type' => 'string', 'default' => 'comments'],
        'show_icon' => ['type' => 'boolean', 'default' => false],
        'icon' => ['type' => 'string', 'nullable' => true],
        'link_to_comments' => ['type' => 'boolean', 'default' => true],
    ],
    'supports' => ['spacing', 'typography', 'colors'],
]
```

Features:
- Display comment count
- Number, short, or long format
- Custom singular/plural labels
- Optional icon
- Link to comments section

Formats:
```
number: "5"
short: "5 comments"
long: "5 people have commented"
zero: "No comments yet" or "Be the first to comment"
```

## Alternatives Considered

- Third-party comment service (Disqus, etc.) (rejected: want self-hosted)
- Single monolithic comment block (rejected: not flexible enough)
- No comment system (rejected: common requirement)

## Use Cases

1. User adds complete comment system to blog posts
2. User displays comments without form (read-only)
3. User adds comment form to custom page
4. User shows comment count in post listings
5. User requires login to comment
6. User enables threaded discussions
7. Admin moderates comments before publication

## Acceptance Criteria

- [ ] Comments section displays comments and form
- [ ] Comments list shows threaded comments
- [ ] Comments list supports pagination
- [ ] Comment form submits comments
- [ ] Comment form validates required fields
- [ ] Comment form has spam protection
- [ ] Comment count displays correctly
- [ ] Comment count links to comments section
- [ ] Reply functionality works for threaded comments
- [ ] Moderation queue holds comments for approval
- [ ] Users can edit/delete their own comments
- [ ] Admin can moderate all comments

---

**Related Issues:**
- Depends on: Block Registry, User Authentication
- Related: Avatar Block (#029), Moderation System
