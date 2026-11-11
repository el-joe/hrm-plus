#!/usr/bin/env bash
# One-time LOCAL setup for http://hrm-plus.loc (needs root). Idempotent. Run:  sudo /var/www/hrm-plus/deploy/setup-local-sudo.sh
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run with sudo" >&2; exit 1; }
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
a2enmod -q rewrite
install -m 0644 -o root -g root "$DIR/apache/hrm-plus.loc.conf" /etc/apache2/sites-available/hrm-plus.loc.conf
a2ensite -q hrm-plus.loc
grep -qE '^[[:space:]]*127\.0\.0\.1[[:space:]]+hrm-plus\.loc([[:space:]]|$)' /etc/hosts || echo '127.0.0.1 hrm-plus.loc' >> /etc/hosts
apache2ctl configtest
systemctl reload apache2
curl -s -o /dev/null -w "hrm-plus.loc/admin/login -> HTTP %{http_code}\n" http://hrm-plus.loc/admin/login || true
