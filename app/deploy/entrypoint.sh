#!/bin/sh
set -eu
if [ -n "${APP_KEY_FILE:-}" ]; then export APP_KEY="$(cat "$APP_KEY_FILE")"; fi
if [ -n "${DB_PASSWORD_FILE:-}" ]; then export DB_PASSWORD="$(cat "$DB_PASSWORD_FILE")"; fi
exec "$@"
