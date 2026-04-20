# Packaging the monorepo

The `visual-editor` repo is transitioning to a monorepo that ships multiple
Composer packages out of `packages/*`. This file describes how those
sub-packages are meant to reach Packagist, because Packagist watches one
`composer.json` per repo and cannot see nested `composer.json` files directly.

## Sub-packages

| Path                                    | Composer name                                       | Status      |
|-----------------------------------------|-----------------------------------------------------|-------------|
| `./`                                    | `artisanpack-ui/visual-editor`                      | published   |
| `packages/visual-editor-renderer-blade` | `artisanpack-ui/visual-editor-renderer-blade`       | unpublished |

M10 (React renderer) and M11 (Vue renderer) will add two more sub-packages
under the same `packages/` directory.

## Distribution strategy: subtree splits

Each sub-package is mirrored to its own standalone read-only repo, and those
mirrors are what Packagist tracks. This is the same approach Laravel Framework
uses for `illuminate/*` and Symfony uses for `symfony/*`.

The canonical tool is [`splitsh/lite`](https://github.com/splitsh/lite): given
a subdirectory and a branch, it produces a new commit tree that is equivalent
to the subdirectory's history and pushes it to the mirror repo.

### Expected mirror repos

- `github.com/ArtisanPack-UI/visual-editor-renderer-blade`
- `github.com/ArtisanPack-UI/visual-editor-renderer-react` (M10)
- `github.com/ArtisanPack-UI/visual-editor-renderer-vue` (M11)

Each mirror's `main` and `release/1.0` branches are overwritten by `splitsh`
on every push. Contributors never open PRs against the mirrors.

### CI wiring (to be added in M15)

A GitHub Actions workflow should run on every push to `release/1.0` and `main`
of this repo:

1. Check out the monorepo with full history.
2. Download `splitsh/lite` binary.
3. For each sub-package, run:
   ```sh
   splitsh-lite --prefix=packages/visual-editor-renderer-blade --origin=refs/heads/<branch>
   ```
4. Force-push the resulting SHA to the mirror repo's matching branch.
5. Replay the release tag onto the mirror for `v1.0.0`-style tags.

The mirror repos then need only one-time setup on Packagist and GitHub
(deploy key with write access from the monorepo CI).

### Interim: local path repository

Until the split workflow lands, the dev app consumes each sub-package via a
path repository in its root `composer.json`:

```json
{
    "type": "path",
    "url": "../../Desktop/ArtisanPack UI Packages/visual-editor/packages/visual-editor-renderer-blade",
    "options": { "symlink": true }
}
```

Host apps outside the monorepo cannot install the sub-packages until the
split workflow is in place.

## Checklist before V1 ship (M15)

- [ ] Create `ArtisanPack-UI/visual-editor-renderer-blade` mirror repo on GitHub.
- [ ] Create deploy key, add as secret to this monorepo's Actions settings.
- [ ] Submit the mirror to Packagist.
- [ ] Add the CI workflow described above.
- [ ] Do a dry-run split on `release/1.0`, verify the mirror's history makes sense.
- [ ] Repeat for M10 and M11 sibling renderers when they land.
