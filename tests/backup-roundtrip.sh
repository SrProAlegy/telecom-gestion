#!/bin/sh
set -eu
export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
restore="telcom_restore_check_$$"
dump="$(mktemp /tmp/telcom-dump.XXXXXX.sql)"
cleanup() {
    mysql -uroot -e "DROP DATABASE IF EXISTS \`$restore\`"
    rm -f "$dump"
}
trap cleanup EXIT
mysqldump -uroot --single-transaction --no-tablespaces --routines --triggers \
    --set-gtid-purged=OFF "$MYSQL_DATABASE" > "$dump"
mysql -uroot -e "CREATE DATABASE \`$restore\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql -uroot "$restore" < "$dump"
tables="$(mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$restore'")"
states="$(mysql -uroot -N "$restore" -e 'SELECT COUNT(*) FROM estados_reparacion')"
version="$(mysql -uroot -N "$restore" -e 'SELECT version FROM schema_version')"
test "$tables" = 14
test "$states" = 8
test "$version" = 1
echo 'OK: respaldo restaurado en base temporal (14 tablas, 8 estados y versión 1).'
