#!/usr/bin/env bash
# File / 文件：scripts/validate_release.sh
# EN: Operations/deployment/diagnostics script owned by this project.
# 中文：该文件是本项目自有的运维、部署或诊断脚本。
# Maintenance / 维护：Preserve validation, safety, and diagnostics when editing. / 修改时保留校验、安全与诊断。
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
  "sales-posts/public/assets/responsive.css"
  "sales-posts/app/Core/ApiAuth.php"
  "sales-posts/app/Controllers/ExternalApiController.php"
  "sales-posts/app/Controllers/GraphqlController.php"
  "sales-posts/docs/API.md"
  "sales-posts/docs/openapi-v1.yaml"
  "sales-posts/docs/schema.graphql"
  "sales-posts/scripts/migrate_v0_2_05_api.php"
  "sales-posts/scripts/audit_bilingual_comments.php"
  "sales-posts/scripts/audit_phpdoc_contract.php"
  "sales-posts/scripts/audit_jsdoc_contract.php"
  "sales-posts/storage/logs/.gitkeep"
  "sales-posts/storage/transfer/.gitkeep"
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
    sales-posts/storage/transfer/*)
      if [[ "$entry" != "sales-posts/storage/transfer/" && "$entry" != "sales-posts/storage/transfer/.gitkeep" ]]; then
        echo "Forbidden database transfer file in release: $entry" >&2
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

# EN: Re-run the bilingual comment audit against the exact files inside the ZIP.
# 中文：针对 ZIP 内的实际文件再次执行双语注释审计，防止打包阶段漏文件或错版本。
VERIFY_TMP="$(mktemp -d)"
trap 'rm -rf "$VERIFY_TMP"' EXIT
unzip -q "$ARCHIVE" -d "$VERIFY_TMP"
php "$VERIFY_TMP/sales-posts/scripts/audit_bilingual_comments.php"
php "$VERIFY_TMP/sales-posts/scripts/audit_phpdoc_contract.php"
php "$VERIFY_TMP/sales-posts/scripts/audit_jsdoc_contract.php"

printf 'Release validation OK: %s (%d entries, version %s)\n' \
  "$ARCHIVE" "${#ENTRIES[@]}" "$archive_version"
