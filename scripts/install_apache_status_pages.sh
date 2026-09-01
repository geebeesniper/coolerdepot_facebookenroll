#!/usr/bin/env bash
# File / 文件：scripts/install_apache_status_pages.sh
# EN: Operations/deployment/diagnostics script owned by this project.
# 中文：该文件是本项目自有的运维、部署或诊断脚本。
# Maintenance / 维护：Preserve validation, safety, and diagnostics when editing. / 修改时保留校验、安全与诊断。
set -euo pipefail

ROOT="/opt/coolerdepot"
CONF="$ROOT/apache/php-fpm.conf"
SNIPPET="$ROOT/www/sales-posts/deploy/apache-global-status-errors.conf"
BEGIN="# BEGIN CDSP GLOBAL STATUS PAGES"
END="# END CDSP GLOBAL STATUS PAGES"

if [[ ! -f "$CONF" ]]; then
  echo "Apache config not found: $CONF" >&2
  exit 1
fi

if [[ ! -f "$SNIPPET" ]]; then
  echo "Status-page snippet not found: $SNIPPET" >&2
  exit 1
fi

cp "$CONF" "$CONF.bak-v0.1.4"

python3 - "$CONF" "$SNIPPET" "$BEGIN" "$END" <<'PY'
from pathlib import Path
import sys

conf = Path(sys.argv[1])
snippet = Path(sys.argv[2]).read_text(encoding="utf-8").strip()
begin = sys.argv[3]
end = sys.argv[4]

text = conf.read_text(encoding="utf-8")

if begin in text and end in text:
    before = text.split(begin, 1)[0].rstrip()
    after = text.split(end, 1)[1].lstrip()
    text = before + "\n\n" + begin + "\n" + snippet + "\n" + end + "\n"
    if after:
        text += "\n" + after
else:
    text = text.rstrip() + "\n\n" + begin + "\n" + snippet + "\n" + end + "\n"

conf.write_text(text, encoding="utf-8")
PY

cd "$ROOT"

echo "Rebuilding Apache..."
docker compose build apache
docker compose up -d --force-recreate apache

echo "Checking Apache syntax..."
docker compose exec apache httpd -t

echo
echo "Installed global status pages."
echo "Test:"
echo "  curl -i -u 'YOUR_BASIC_AUTH_USER:YOUR_BASIC_AUTH_PASSWORD' http://127.0.0.1/this-page-does-not-exist"
