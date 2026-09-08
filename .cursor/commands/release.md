---
name: release
description: Run the release gate — adversarial review, then SemVer tag (master release or dev pre-release).
---

Use the project `release-gate` skill.

Then use the `adversarial-multimodel-review` skill to review the work being tagged. Follow that skill's full workflow (evidence packet in `.adversarial-review/`, runtime-resolved critic models, synthesis, round cap).

Do not create a tag or GitHub Release unless the verdict is `SAFE TO COMMIT` or `SAFE TO DEPLOY AFTER RUNTIME CHECK` with required runtime checks done.

Tag `master` as `X.Y.Z` (GitHub Release). Tag `dev` as `X.Y.Z-<pre>.N` (GitHub Release `--prerelease`). Ask before pushing tags.
