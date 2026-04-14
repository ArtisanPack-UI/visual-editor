# Phase 1 — REST API

Three JSON endpoints feed the React editor during Phase 1. All routes sit behind the `api` + `auth` middleware stack and are grouped under the `/visual-editor/api` prefix.

| Method | URI                               | Name                            | Purpose                                          |
| ------ | --------------------------------- | ------------------------------- | ------------------------------------------------ |
| GET    | `/visual-editor/api/posts/{post}` | `visual-editor.api.posts.show`  | Return the block tree JSON for a post            |
| PUT    | `/visual-editor/api/posts/{post}` | `visual-editor.api.posts.update`| Persist a new block tree for a post              |
| GET    | `/visual-editor/api/blocks`       | `visual-editor.api.blocks.index`| Return the registered block type definitions    |

The middleware stack is overridable via `config('artisanpack.visual-editor.api.middleware')` if a consuming app needs Sanctum, a different guard, etc.

## Authorization

`VisualEditorPostPolicy` backs both post endpoints. Abilities:

- `view` — requires an authenticated user; when `artisanpack.visual-editor.authorization.restrict_by_owner` is `true`, the authenticated user must match `ve_contents.author_id`.
- `update` — same rules as `view`.

Unauthenticated requests receive `401`. Authenticated requests without the needed ability receive `403`.

## Block tree shape

All blocks are objects of the following recursive shape:

```json
{
  "clientId": "string (required, unique within the tree)",
  "name": "string (required, e.g. \"core/paragraph\")",
  "attributes": { },
  "innerBlocks": []
}
```

`UpdatePostBlocksRequest` runs the custom `BlockTreeRule` against the `blocks` key. The rule walks the tree and fails on the first block that is missing one of the four required keys or has the wrong type.

## GET `/visual-editor/api/posts/{post}`

Successful response (`200`):

```json
{
  "id": 1,
  "title": "Test Post",
  "blocks": [
    {
      "clientId": "abc-123",
      "name": "core/paragraph",
      "attributes": { "content": "Hello" },
      "innerBlocks": []
    }
  ],
  "updated_at": "2026-04-14T12:34:56+00:00"
}
```

## PUT `/visual-editor/api/posts/{post}`

Request body:

```json
{
  "blocks": [
    {
      "clientId": "heading-1",
      "name": "core/heading",
      "attributes": { "content": "Updated", "level": 2 },
      "innerBlocks": []
    }
  ]
}
```

Responses:

- `200` — returns the same JSON shape as the `show` endpoint with the persisted tree.
- `422` — `blocks` is missing, not an array, or fails the recursive shape check. The error bag reports the first invalid path in dot notation (for example `blocks.1.innerBlocks.0.attributes`).

## GET `/visual-editor/api/blocks`

Returns the list of registered block types from `BlockTypeRegistry`. Phase 1 ships with `core/paragraph` and `core/heading`; downstream packages extend the registry through the service container.

```json
{
  "data": [
    {
      "name": "core/paragraph",
      "title": "Paragraph",
      "category": "text",
      "attributes": {
        "content": { "type": "string", "default": "" }
      }
    },
    {
      "name": "core/heading",
      "title": "Heading",
      "category": "text",
      "attributes": {
        "content": { "type": "string", "default": "" },
        "level":   { "type": "integer", "default": 2 }
      }
    }
  ]
}
```
