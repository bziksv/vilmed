# >>> VILMED catalog gate (до PHP, 2026-11-07)
# Включается в server { } vilmed.ru (vilmed.ru.includes).
# 1) Перебор: section_id три раза и больше — соединение закрывается.
# 2) Обычный каталог и карточка без куки vm_ok не запускают PHP.
#    Браузер ставит куку и сразу открывает ту же страницу.
#    Одноразовый заход с нового IP остаётся на короткой странице.
# Поиск, сортировка и номер страницы (?q, sort, view, PAGEN, section_id)
# сюда не входят: их по-прежнему проверяет капча в PHP.
# IP из $search_engine и свой прогрев не режем. POST не режем.
# add_header в if на уровне server запрещён, поэтому ответ отдаёт /__vm_ok.
set $vm_farm 0;
if ($request_uri ~* "^/catalog/.*section_id.*section_id.*section_id") {
    set $vm_farm 1;
}
if ($request_method !~ ^(GET|HEAD)$) {
    set $vm_farm 0;
}
if ($search_engine = 1) {
    set $vm_farm 0;
}
if ($vm_farm = 1) {
    return 444;
}

set $vm_chk 0;
if ($request_uri ~* "^/(catalog|product)/") {
    set $vm_chk 1;
}
if ($request_method !~ ^(GET|HEAD)$) {
    set $vm_chk 0;
}
if ($request_uri ~* "[?&](q|sort|view|section_id|PAGEN)") {
    set $vm_chk 0;
}
if ($cookie_vm_ok = "1") {
    set $vm_chk 0;
}
if ($search_engine = 1) {
    set $vm_chk 0;
}
if ($http_user_agent ~* "(vilmed-warmup|vilmed-psi)") {
    set $vm_chk 0;
}
if ($vm_chk = 1) {
    rewrite ^ /__vm_ok last;
}

location = /__vm_ok {
    internal;
    default_type "text/html; charset=utf-8";
    add_header Set-Cookie "vm_ok=1; Path=/; Max-Age=604800; SameSite=Lax" always;
    add_header Cache-Control "no-store" always;
    add_header X-Robots-Tag "noindex" always;
    return 200 '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Вилмед</title></head><body><p id="m">Открываем страницу…</p><script>document.cookie="vm_ok=1;path=/;max-age=604800;samesite=lax";if(document.cookie.indexOf("vm_ok=1")>-1){location.reload();}else{document.getElementById("m").innerHTML="Включите cookie в браузере, чтобы открыть страницу.";}</script></body></html>';
}
# <<< VILMED catalog gate
