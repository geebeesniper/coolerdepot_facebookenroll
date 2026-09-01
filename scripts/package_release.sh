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
  --include='.env.example' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='*.zip' \
  --exclude='*.tgz' \
  --exclude='*.tar.gz' \
  --exclude='*.bak' \
  --exclude='*.dump' \
  --exclude='SALES-LIST*.csv' \
  --include='storage/uploads/.gitkeep' \
  --include='storage/logs/.gitkeep' \
  --exclude='storage/uploads/*' \
  --exclude='storage/logs/*' \
  "$ROOT/" "$TMP/sales-posts/"

# Fail closed if an exclusion rule is ever weakened later.
if [[ -e "$TMP/sales-posts/.env" ]]; then
  echo 'Refusing to package production .env.' >&2
  exit 1
fi

# If packaging on a production server, make sure credential values from the
# local .env were not accidentally hard-coded into another release file.
# Secret values are never printed; only the environment key is reported.
if [[ -f "$ROOT/.env" ]]; then
  while IFS='=' read -r key raw_value; do
    key="$(printf '%s' "$key" | xargs)"
    [[ -z "$key" || "$key" == \#* ]] && continue

    case "$key" in
      *PASSWORD*|*SECRET*|*TOKEN*|*API_KEY*|*PRIVATE_KEY*|*CREDENTIAL*) ;;
      *) continue ;;
    esac

    value="$(printf '%s' "$raw_value" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    if [[ "$value" == \"*\" && "$value" == *\" ]]; then
      value="${value:1:${#value}-2}"
    elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
      value="${value:1:${#value}-2}"
    fi

    [[ ${#value} -lt 8 ]] && continue

    if grep -RIlF --exclude='.env.example' -- "$value" "$TMP/sales-posts" >/dev/null 2>&1; then
      echo "Refusing release: production credential value leaked from $key." >&2
      exit 1
    fi
  done < "$ROOT/.env"
fi

mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"
(
  cd "$TMP"
  zip -qr "$OUT" sales-posts
)

"$ROOT/scripts/validate_release.sh" "$OUT" "$VERSION"
echo "$OUT"
