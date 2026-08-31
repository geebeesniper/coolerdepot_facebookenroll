#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION")"
LABEL="${1:-release}"
OUT="${2:-$ROOT/../sales-posts-v${VERSION}-${LABEL}.zip}"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/sales-posts"
rsync -a \
  --exclude='.git/' \
  --exclude='*.zip' \
  --exclude='SALES-LIST*.csv' \
  --exclude='storage/uploads/*' \
  "$ROOT/" "$TMP/sales-posts/"

(
  cd "$TMP"
  zip -qr "$OUT" sales-posts
)

echo "$OUT"
