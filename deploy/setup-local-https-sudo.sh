#!/usr/bin/env bash
# One-time LOCAL HTTPS setup for https://hrm-plus.loc (needs root). Idempotent.
# Run:  sudo /var/www/hrm-plus/deploy/setup-local-https-sudo.sh
# Uses the dev CA + certificate in ~/.local/share/hrm-plus-ca of the invoking user.
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run with sudo" >&2; exit 1; }
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
USER_HOME="$(getent passwd "${SUDO_USER:-root}" | cut -d: -f6)"
CA_DIR="$USER_HOME/.local/share/hrm-plus-ca"
for f in ca.crt hrm-plus.loc.crt hrm-plus.loc.key; do [[ -f "$CA_DIR/$f" ]] || { echo "missing $CA_DIR/$f" >&2; exit 1; }; done

# 1. certificate for Apache
install -d -m 0755 /etc/ssl/hrm-plus
install -m 0644 "$CA_DIR/hrm-plus.loc.crt" /etc/ssl/hrm-plus/hrm-plus.loc.crt
install -m 0600 "$CA_DIR/hrm-plus.loc.key" /etc/ssl/hrm-plus/hrm-plus.loc.key

# 2. Apache: ssl module + HTTPS vhost + HTTP->HTTPS redirect vhost
a2enmod -q ssl headers rewrite
install -m 0644 "$DIR/apache/hrm-plus.loc-ssl.conf" /etc/apache2/sites-available/hrm-plus.loc-ssl.conf
install -m 0644 "$DIR/apache/hrm-plus.loc.conf" /etc/apache2/sites-available/hrm-plus.loc.conf
a2ensite -q hrm-plus.loc hrm-plus.loc-ssl
grep -qE '^[[:space:]]*127\.0\.0\.1[[:space:]]+hrm-plus\.loc([[:space:]]|$)' /etc/hosts || echo '127.0.0.1 hrm-plus.loc' >> /etc/hosts
apache2ctl configtest
systemctl reload apache2

# 3. trust the dev CA: system store (curl, PHP) ...
install -m 0644 "$CA_DIR/ca.crt" /usr/local/share/ca-certificates/hrm-plus-local-dev-ca.crt
update-ca-certificates >/dev/null

# ... and the user's NSS store (Chrome/Chromium/Electron on Linux)
command -v certutil >/dev/null || { apt-get install -y -qq libnss3-tools >/dev/null; }
if [[ -n "${SUDO_USER:-}" ]]; then
  sudo -u "$SUDO_USER" mkdir -p "$USER_HOME/.pki/nssdb"
  [[ -f "$USER_HOME/.pki/nssdb/cert9.db" ]] || sudo -u "$SUDO_USER" certutil -d "sql:$USER_HOME/.pki/nssdb" -N --empty-password
  sudo -u "$SUDO_USER" certutil -d "sql:$USER_HOME/.pki/nssdb" -D -n "HRM Plus Local Dev CA" 2>/dev/null || true
  sudo -u "$SUDO_USER" certutil -d "sql:$USER_HOME/.pki/nssdb" -A -t "C,," -n "HRM Plus Local Dev CA" -i "$CA_DIR/ca.crt"
  # Firefox profiles (if any)
  for p in "$USER_HOME"/.mozilla/firefox/*.default* "$USER_HOME"/snap/firefox/common/.mozilla/firefox/*.default*; do
    [[ -f "$p/cert9.db" ]] || continue
    sudo -u "$SUDO_USER" certutil -d "sql:$p" -D -n "HRM Plus Local Dev CA" 2>/dev/null || true
    sudo -u "$SUDO_USER" certutil -d "sql:$p" -A -t "C,," -n "HRM Plus Local Dev CA" -i "$CA_DIR/ca.crt"
  done
fi

echo "OK: https://hrm-plus.loc (http redirects to https). Restart the browser to pick up the new CA."
curl -s -o /dev/null -w "https://hrm-plus.loc/admin/login -> HTTP %{http_code}\n" https://hrm-plus.loc/admin/login || true
