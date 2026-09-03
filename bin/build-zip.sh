#!/usr/bin/env bash
# bin/build-zip.sh
# Builds a distributable ZIP of the plugin.
# Usage: bash bin/build-zip.sh [custom-version]
#   custom-version  if provided, overrides the version read from plugin header
#
# Requires: rsync, zip, composer

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DISTIGNORE="$REPO_ROOT/.distignore"

# ---------------------------------------------------------------------------
# Preflight
#
# Fail here rather than half way through: without composer the build dies after
# staging, which reads like a packaging bug instead of a missing tool. A host
# with no PHP toolchain can supply one the way bin/docker-compose does --
# a shim on PATH that execs into a container.
# ---------------------------------------------------------------------------
MISSING=()
for tool in rsync zip composer; do
    command -v "$tool" > /dev/null 2>&1 || MISSING+=("$tool")
done

if [[ ${#MISSING[@]} -gt 0 ]]; then
    echo "ERROR: missing required tool(s): ${MISSING[*]}" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Resolve slug and main file
#
# Both are derived from the one file in the repo root carrying a "Plugin Name:"
# header, so renaming the plugin means renaming exactly one file and nothing
# here. The slug used to be a literal, which is how a rename silently produced
# a package whose folder no longer matched the plugin.
# ---------------------------------------------------------------------------
MAIN_FILE=$(grep -l '^ \* Plugin Name:' "$REPO_ROOT"/*.php 2>/dev/null | head -1)

if [[ -z "$MAIN_FILE" ]]; then
    echo "ERROR: no PHP file in the repo root declares a Plugin Name header." >&2
    exit 1
fi

PLUGIN_SLUG=$(basename "$MAIN_FILE" .php)

# ---------------------------------------------------------------------------
# Resolve version
# ---------------------------------------------------------------------------
if [[ "${1:-}" != "" ]]; then
    VERSION="$1"
else
    VERSION=$(grep -m1 '^ \* Version:' "$MAIN_FILE" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
fi

if [[ -z "$VERSION" ]]; then
    echo "ERROR: Could not determine plugin version." >&2
    exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="$REPO_ROOT/$ZIP_NAME"
BUILD_DIR=$(mktemp -d)
PLUGIN_DIR="$BUILD_DIR/$PLUGIN_SLUG"

echo "==> Building $ZIP_NAME"
echo "    slug    : $PLUGIN_SLUG"
echo "    version : $VERSION"
echo "    source  : $REPO_ROOT"
echo "    tmp dir : $BUILD_DIR"

# ---------------------------------------------------------------------------
# Copy runtime files (honouring .distignore)
# ---------------------------------------------------------------------------
mkdir -p "$PLUGIN_DIR"

# Build an rsync --exclude list from .distignore (skip blank lines and comments)
EXCLUDES=()
while IFS= read -r line; do
    [[ -z "$line" || "$line" == \#* ]] && continue
    EXCLUDES+=("--exclude=${line}")
done < "$DISTIGNORE"

rsync -a --no-owner --no-group --copy-unsafe-links \
    "${EXCLUDES[@]}" \
    "$REPO_ROOT/" "$PLUGIN_DIR/"

# ---------------------------------------------------------------------------
# Install production dependencies (the NFe.io SDK) into the staging directory
#
# The repo's own vendor/ is excluded by .distignore because it carries the dev
# tooling. A clean --no-dev install here is what actually ships, so the plugin
# can load the SDK on a site that has never seen Composer.
#
# --classmap-authoritative is what makes the SDK floor matter: nfe/nfe releases
# 3.0 through 3.4.0 shipped files with malformed type hints that are a fatal
# parse error under an authoritative classmap. composer.json pins ^3.5.
# ---------------------------------------------------------------------------
# composer.json/composer.lock arrive with the rsync above (they are deliberately
# absent from .distignore) and stay in the package: Plugin Check reports
# missing_composer_json_file when vendor/ ships without its manifest.
if [[ ! -f "$PLUGIN_DIR/composer.json" ]]; then
    echo "ERROR: composer.json did not reach the staging directory; check .distignore." >&2
    rm -rf "$BUILD_DIR"
    exit 1
fi

echo "==> Installing production dependencies"
( cd "$PLUGIN_DIR" && composer install \
    --no-dev \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-interaction \
    --quiet )

if [[ ! -f "$PLUGIN_DIR/vendor/autoload.php" ]]; then
    echo "ERROR: composer install produced no autoloader; refusing to ship a broken zip." >&2
    rm -rf "$BUILD_DIR"
    exit 1
fi

# Guard against shipping dev tooling by accident.
for forbidden in phpunit squizlabs wp-coding-standards phpcompatibility; do
    if [[ -d "$PLUGIN_DIR/vendor/$forbidden" ]]; then
        echo "ERROR: dev dependency '$forbidden' ended up in the package." >&2
        rm -rf "$BUILD_DIR"
        exit 1
    fi
done

# ---------------------------------------------------------------------------
# Create ZIP (plugin folder must be the top-level entry inside the archive)
# ---------------------------------------------------------------------------
cd "$BUILD_DIR"

# Start from nothing. `zip` *adds to* an existing archive instead of replacing
# it, so rebuilding over a previous zip silently kept entries that no longer
# exist in the source -- including files deleted on purpose.
rm -f "$ZIP_PATH"

zip -r "$ZIP_PATH" "$PLUGIN_SLUG" -x "*.DS_Store" -x "__MACOSX/*" > /dev/null

# ---------------------------------------------------------------------------
# Cleanup
# ---------------------------------------------------------------------------
rm -rf "$BUILD_DIR"

echo "==> Done: $ZIP_PATH"
