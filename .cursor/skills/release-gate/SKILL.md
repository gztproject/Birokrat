---
name: release-gate
description: Cuts SemVer tags after adversarial multi-model review. Use when tagging, releasing, shipping, creating a GitHub Release, or when the user asks to cut a master release or a dev pre-release.
---

# Release gate

Run this before any version tag. Ordinary `feature/*` / `bugfix/*` commits do not use this skill.

## 1. Confirm git state

- Release tag: current branch is `master` (or you are about to merge `dev` → `master` and tag that merge).
- Pre-release tag: current branch is `dev`.
- Working tree clean except intentional release metadata. Topic-branch work must already be merged.
- Proposed tag:
  - `master`: `X.Y.Z` (release). Match existing style: no `v` prefix (`0.0.5-stable` was a prior release tag).
  - `dev`: `X.Y.Z-rc.N` or another SemVer pre-release identifier (`-beta.N`, `-dev.N`).
- Stop if HEAD is already tagged, or if the tag exists.

## 2. Adversarial review

Read and execute the `adversarial-multimodel-review` skill in full. Do not summarize it from memory.

Release-specific packet notes:

- `DIFF.patch`: `git diff master..HEAD` when tagging `dev`; the merge range vs previous release tag when tagging `master`.
- Target decision: deploy (`master`) or tag (`dev`).
- Include PHPUnit / relevant CI evidence in `TESTS.txt`.

Do not tag on `BLOCK`, `FIX FIRST`, or `INSUFFICIENT EVIDENCE`.

## 3. Tag and publish (only after a passing verdict)

Ask the user before pushing tags or creating GitHub releases.

```bash
git tag -a "$TAG" -m "$MESSAGE"
# master:
gh release create "$TAG" --title "$TAG" --notes "$NOTES"
# dev:
gh release create "$TAG" --prerelease --title "$TAG" --notes "$NOTES"
```

Push the tag only if the user asks (`git push origin "$TAG"` and the branch).

## 4. After a master release

Merge or cherry-pick back so `dev` contains the release commit if it does not already.
