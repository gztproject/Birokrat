#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

export APP_ENV=prod
export APP_DEBUG=0

prompt() {
	local reply
	read -r -p "$1" reply </dev/tty
	printf '%s' "$reply"
}

is_prerelease() {
	[[ "$1" == *-* && "$1" != *-stable ]]
}

current_version() {
	git describe --tags --exact-match HEAD 2>/dev/null \
		|| git describe --tags --always HEAD 2>/dev/null \
		|| printf 'unknown'
}

prepend_path() {
	[[ -d "$1" ]] || return 0
	case ":${PATH}:" in
		*":$1:"*) ;;
		*) PATH="$1:${PATH}" ;;
	esac
}

add_nvm_node_bin() {
	local nvm_nodes="${1}/.nvm/versions/node"
	[[ -d "$nvm_nodes" ]] || return 0
	local latest
	latest="$(find "$nvm_nodes" -mindepth 1 -maxdepth 1 -type d -name 'v*' | sort -V | tail -1)"
	[[ -n "$latest" && -x "${latest}/bin/node" ]] && prepend_path "${latest}/bin"
}

# sudo -u www-data -H replaces PATH. Node is often only in the operator's nvm.
setup_path() {
	prepend_path /usr/local/bin
	prepend_path /usr/bin
	add_nvm_node_bin "${HOME:-}"
	if [[ -n "${SUDO_USER:-}" ]]; then
		local sudo_home
		sudo_home="$(getent passwd "$SUDO_USER" | cut -d: -f6)"
		add_nvm_node_bin "$sudo_home"
		prepend_path "${sudo_home}/.local/bin"
		prepend_path "${sudo_home}/.volta/bin"
	fi
	export PATH
}

need_cmd() {
	if ! command -v "$1" >/dev/null 2>&1; then
		echo "Missing command: $1 (not in PATH for $(whoami))" >&2
		echo "sudo resets PATH. Either install Node.js system-wide, or pass your PATH:" >&2
		echo "  sudo -u www-data -H env PATH=\"\$PATH\" bash ./Update.sh" >&2
		echo "Check as your own user: command -v node; command -v npm" >&2
		exit 1
	fi
}

echo "Birokrat update"
echo "---------------"
if [[ "$(id -u)" -eq 0 ]]; then
	echo "Do not run as root. Use the web user, for example: sudo -u www-data -H bash $0" >&2
	exit 1
fi

setup_path
need_cmd git
need_cmd php
need_cmd composer
need_cmd npm
need_cmd node
echo "Using node $(command -v node) ($(node -v)), npm $(command -v npm)"

echo
echo "Fetching tags from origin (working tree is not changed yet)..."
git fetch origin --tags --prune

mapfile -t tags < <(git tag -l --sort=-v:refname)
if [[ ${#tags[@]} -eq 0 ]]; then
	echo "No tags found on origin. Tag a release on master before updating this server." >&2
	exit 1
fi

current="$(current_version)"
head_sha="$(git rev-parse --short HEAD)"
echo
echo "Current checkout: ${current}  (${head_sha})"
if [[ -n "$(git status --porcelain)" ]]; then
	echo "Working tree has local changes. A confirmed update will discard them (untracked files such as .env.local are kept)."
fi

releases=()
prereleases=()
for tag in "${tags[@]}"; do
	if is_prerelease "$tag"; then
		prereleases+=("$tag")
	else
		releases+=("$tag")
	fi
done

echo
if [[ ${#releases[@]} -gt 0 ]]; then
	echo "Releases (production):"
	for tag in "${releases[@]}"; do
		if [[ "$tag" == "$current" ]]; then
			echo "  ${tag}  (current)"
		else
			echo "  ${tag}"
		fi
	done
else
	echo "No production release tags (X.Y.Z) found."
fi

if [[ ${#prereleases[@]} -gt 0 ]]; then
	echo
	echo "Pre-releases (not for production unless you intend to test):"
	for tag in "${prereleases[@]}"; do
		if [[ "$tag" == "$current" ]]; then
			echo "  ${tag}  (current)"
		else
			echo "  ${tag}"
		fi
	done
fi

default=""
if [[ ${#releases[@]} -gt 0 ]]; then
	default="${releases[0]}"
fi

echo
echo "This server should normally track a release tag, not master and not an rc."
if [[ -n "$default" ]]; then
	choice="$(prompt "Version to install [${default}] (tag name, or q to quit): ")"
	choice="${choice:-$default}"
else
	choice="$(prompt "Version to install (tag name, or q to quit): ")"
fi

if [[ -z "$choice" || "$choice" == "q" || "$choice" == "Q" ]]; then
	echo "Aborted. Nothing was changed."
	exit 0
fi

if ! git rev-parse -q --verify "refs/tags/${choice}" >/dev/null; then
	echo "Unknown tag: ${choice}" >&2
	echo "Use one of the names listed above." >&2
	exit 1
fi

if is_prerelease "$choice"; then
	echo
	echo "Warning: ${choice} is a pre-release."
	confirm_pre="$(prompt "Install a pre-release on this server? [y/N]: ")"
	if [[ "${confirm_pre}" != "y" && "${confirm_pre}" != "Y" ]]; then
		echo "Aborted. Nothing was changed."
		exit 0
	fi
fi

if [[ "$choice" == "$current" ]]; then
	echo
	echo "Already on ${choice}."
	rebuild="$(prompt "Re-run install, assets, migrations, and cache anyway? [y/N]: ")"
	if [[ "${rebuild}" != "y" && "${rebuild}" != "Y" ]]; then
		echo "Aborted. Nothing was changed."
		exit 0
	fi
else
	echo
	echo "Will discard tracked local changes and check out tag ${choice}."
	confirm="$(prompt "Proceed? [y/N]: ")"
	if [[ "${confirm}" != "y" && "${confirm}" != "Y" ]]; then
		echo "Aborted. Nothing was changed."
		exit 0
	fi
	git checkout --detach --force "$choice"
fi

echo
echo "Installing ${choice}..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
composer dump-env prod

# Encore and webpack live in devDependencies; do not use --omit=dev.
npm ci
npm run build

php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod

echo
echo "Done. Running $(current_version)."
