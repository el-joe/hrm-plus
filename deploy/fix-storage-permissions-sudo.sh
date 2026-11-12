#!/usr/bin/env bash
# Make storage/ and bootstrap/cache shared between the web server (www-data) and CLI users in the www-data group.
# Run once (and after copying files in as another user):  sudo /var/www/hrm-plus/deploy/fix-storage-permissions-sudo.sh
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run with sudo" >&2; exit 1; }
APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/../backend" && pwd)"
for d in "$APP/storage" "$APP/bootstrap/cache"; do
  chgrp -R www-data "$d"
  chmod -R g+rwX,o-rwx "$d"
  find "$d" -type d -exec chmod g+s {} +
done
echo "OK: $APP/storage and bootstrap/cache are group www-data, group-writable, setgid."
