#!/bin/sh
set -eu

APP_ROOT="/var/www/html"
STORAGE_ROOT="/data"

# Ensure Apache uses the mod_php-compatible MPM at container startup.
a2dismod mpm_event >/dev/null 2>&1 || true
a2dismod mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

prepare_path() {
    name="$1"
    target="$STORAGE_ROOT/$name"
    link="$APP_ROOT/$name"

    mkdir -p "$target"
    chown -R www-data:www-data "$target"

    if [ -L "$link" ]; then
        rm -f "$link"
    elif [ -e "$link" ]; then
        rm -rf "$link"
    fi

    ln -s "$target" "$link"
}

prepare_path "uploads"
prepare_path "screenshots"
prepare_path "tmp"

touch "$STORAGE_ROOT/screenshot_debug.log"
chown www-data:www-data "$STORAGE_ROOT/screenshot_debug.log"

if [ -L "$APP_ROOT/screenshot_debug.log" ]; then
    rm -f "$APP_ROOT/screenshot_debug.log"
elif [ -e "$APP_ROOT/screenshot_debug.log" ]; then
    rm -f "$APP_ROOT/screenshot_debug.log"
fi

ln -s "$STORAGE_ROOT/screenshot_debug.log" "$APP_ROOT/screenshot_debug.log"

exec "$@"
