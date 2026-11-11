#!/bin/bash
# Bitrix sites on s2 (except vilmed): PHP 60s public / 600s /bitrix/admin/, nginx 120s / 600s admin.
set -euo pipefail

INC=/var/www/vilmed_ru_usr/data/www/vilmed.ru/tools/server/nginx/bitrix-admin-timeout.inc
SITES="fasad36_ru_usr/fasad36.ru riester_su_usr87/riester.su medplakaty_r_usr/medplakaty.ru lormag_ru_usr/lormag.ru amplipuls_su_usr/gipergidroz.su"
TS=$(date +%Y%m%d-%H%M%S)

set_ini() {
    local file=$1 value=$2 owner=$3
    if [ -f "$file" ] && grep -q '^\s*max_execution_time' "$file"; then
        sed -i "s/^\s*max_execution_time.*/max_execution_time = $value/" "$file"
    else
        echo "max_execution_time = $value" >> "$file"
    fi
    chown "$owner:$owner" "$file"
}

for s in $SITES; do
    u=${s%/*}; n=${s#*/}
    root=/var/www/$u/data/www/$n
    set_ini "$root/.user.ini" 60 "$u"
    [ -d "$root/bitrix/admin" ] && set_ini "$root/bitrix/admin/.user.ini" 600 "$u"

    incf=/etc/nginx/fastpanel2-sites/$u/$n.includes
    touch "$incf"
    if ! grep -q '>>> VM bitrix admin timeout' "$incf"; then
        cp -a "$incf" "$incf.bak.$TS"
        cat "$INC" >> "$incf"
    fi
done

nginx -t && systemctl reload nginx
