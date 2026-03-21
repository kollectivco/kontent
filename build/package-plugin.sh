#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname "$0")" && pwd)"
PLUGIN_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)"
SLUG="kontentainment-charts"
BUILD_DIR="$PLUGIN_ROOT/.build/$SLUG"
DIST_DIR="$PLUGIN_ROOT/dist"
ZIP_PATH="$DIST_DIR/$SLUG.zip"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$SLUG" "$DIST_DIR"

rsync -a \
  --delete \
  --exclude '.git' \
  --exclude '.DS_Store' \
  --exclude '.build' \
  --exclude 'dist' \
  --exclude 'build' \
  "$PLUGIN_ROOT/" "$BUILD_DIR/$SLUG/"

cd "$BUILD_DIR"
rm -f "$ZIP_PATH"
zip -qr "$ZIP_PATH" "$SLUG"
echo "$ZIP_PATH"
