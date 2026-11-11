# >>> VILMED catalog gate (до PHP, 2026-11-11)
# Включается в server { } vilmed.ru (vilmed.ru.includes).
# Переменные $vm_* — /etc/nginx/conf.d/01-vm-guard.conf (tools/server/nginx/).
# Подтверждённых поисковых роботов ($vm_search_engine) правила не трогают.

# Перебор: section_id три раза и больше — соединение закрывается.
set $vm_farm 0;
if ($request_uri ~* "^/catalog/.*section_id.*section_id.*section_id") {
    set $vm_farm 1;
}
if ($request_method !~ ^(GET|HEAD)$) {
    set $vm_farm 0;
}
if ($vm_search_engine = 1) {
    set $vm_farm 0;
}
if ($vm_farm = 1) {
    return 444;
}

# Реферер vilmed.ru, у которого путь без слеша на конце: у сайта все адреса
# со слешем, адрес без слеша сразу уходит в 301, браузер такой не пришлёт.
# Статику не проверяем: шрифты и картинки из CSS приходят с реферером-файлом.
set $vm_badref 0;
if ($http_referer ~* "^https?://(www\.)?vilmed\.ru(/[^?#]*[^/?#])?([?#].*)?$") {
    set $vm_badref 1;
}
if ($http_referer ~* "^https?://(www\.)?vilmed\.ru/[^?#]*\.(php|html?|css|js|json|xml)([?#].*)?$") {
    set $vm_badref 0;
}
if ($vm_dyn_uri = 0) {
    set $vm_badref 0;
}
if ($vm_search_engine = 1) {
    set $vm_badref 0;
}
if ($vm_badref = 1) {
    return 444;
}

# Каталог и карточка для адресов вне России и соседних стран: без куки vm_ok
# сначала короткая страница, кука ставится только скриптом. Бот без JS дальше
# не проходит, PHP не запускается. Роботов ($vm_claims_bot) не трогаем:
# непроверенных ограничивает limit_req, поддельных режет vm-guard.conf.
set $vm_chk 0;
if ($request_uri ~* "^/(catalog|product)/") {
    set $vm_chk 1;
}
if ($request_method !~ ^(GET|HEAD)$) {
    set $vm_chk 0;
}
if ($cookie_vm_ok = "1") {
    set $vm_chk 0;
}
if ($vm_geo_local = 1) {
    set $vm_chk 0;
}
if ($vm_claims_bot = 1) {
    set $vm_chk 0;
}
if ($http_user_agent ~* "(vilmed-warmup|vilmed-psi)") {
    set $vm_chk 0;
}
if ($vm_chk = 1) {
    rewrite ^ /__vm_ok last;
}

# add_header в if на уровне server запрещён, поэтому ответ отдаёт /__vm_ok.
location = /__vm_ok {
    internal;
    default_type "text/html; charset=utf-8";
    add_header Cache-Control "no-store" always;
    add_header X-Robots-Tag "noindex" always;
    return 200 '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Вилмед</title></head><body><p id="m">Открываем страницу…</p><script>document.cookie="vm_ok=1;path=/;max-age=604800;samesite=lax";if(document.cookie.indexOf("vm_ok=1")>-1){location.reload();}else{document.getElementById("m").innerHTML="Включите cookie в браузере, чтобы открыть страницу.";}</script><noscript>Включите JavaScript в браузере, чтобы открыть страницу.</noscript></body></html>';
}
# <<< VILMED catalog gate
