#!/bin/sh
set -e

echo "⏳ Waiting for MySQL at $DB_HOST:$DB_PORT..."

until nc -z "$DB_HOST" "$DB_PORT"; do
  echo "⏳ Waiting for MySQL..."
  sleep 2
done

echo "✅ MySQL is ready!"
exec "$@"
