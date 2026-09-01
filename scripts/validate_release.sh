#!/usr/bin/env bash
set -euo pipefail

ARCHIVE="${1:?Usage: validate_release.sh <release.zip> [expected-version]}"
EXPECTED_VERSION="${2:-}"

if [[ ! -f "$ARCHIVE" ]]; then
  echo "Release archive not found: $ARCHIVE" >&2
  exit 1
fi

# Validate CRCs and central directory before inspecting file names.
unzip -tq "$ARCHIVE" >/dev/null

mapfile -t ENTRIES < <(zipinfo -1 "$ARCHIVE")
if [[ ${#ENTRIES[@]} -eq 0 ]]; then
  echo "Release archive is empty." >&2
  exit 1
fi

required=(
  "sales-posts/VERSION"
  "sales-posts/.env.example"
  "sales-posts/index.php"
  "sales-posts/app/Core/Logger.php"
  "sales-posts/public/assets/diagnostics.js"
  "sales-posts/storage/logs/.gitkeep"
)

for item in "${required[@]}"; do
  if ! printf '%s\n' "${ENTRIES[@]}" | grep -Fxq "$item"; then
    echo "Required release file is missing: $item" >&2
    exit 1
  fi
done

for entry in "${ENTRIES[@]}"; do
  case "$entry" in
    sales-posts/.env)
      echo "Forbidden production environment file in release: $entry" >&2
      exit 1
      ;;
    sales-posts/.env.*)
      if [[ "$entry" != "sales-posts/.env.example" ]]; then
        echo "Forbidden environment file in release: $entry" >&2
        exit 1
      fi
      ;;
    sales-posts/.git/*|sales-posts/.git)
      echo "Forbidden Git metadata in release: $entry" >&2
      exit 1
      ;;
    sales-posts/storage/logs/*)
      if [[ "$entry" != "sales-posts/storage/logs/" && "$entry" != "sales-posts/storage/logs/.gitkeep" ]]; then
        echo "Forbidden runtime log in release: $entry" >&2
        exit 1
      fi
      ;;
    sales-posts/storage/uploads/*)
      if [[ "$entry" != "sales-posts/storage/uploads/" && "$entry" != "sales-posts/storage/uploads/.gitkeep" ]]; then
        echo "Forbidden runtime upload in release: $entry" >&2
        exit 1
      fi
      ;;
    *.zip|*.tgz|*.tar.gz|*.dump|*.bak)
      echo "Forbidden backup/archive nested in release: $entry" >&2
      exit 1
      ;;
    sales-posts/SALES-LIST*.csv)
      echo "Forbidden local Sales CSV in release: $entry" >&2
      exit 1
      ;;
  esac
done

archive_version="$(unzip -p "$ARCHIVE" sales-posts/VERSION | tr -d '[:space:]')"
if [[ -z "$archive_version" ]]; then
  echo "VERSION inside release is empty." >&2
  exit 1
fi

if [[ -n "$EXPECTED_VERSION" && "$archive_version" != "$EXPECTED_VERSION" ]]; then
  echo "Release VERSION mismatch: expected $EXPECTED_VERSION, got $archive_version" >&2
  exit 1
fi

printf 'Release validation OK: %s (%d entries, version %s)\n' \
  "$ARCHIVE" "${#ENTRIES[@]}" "$archive_version"
