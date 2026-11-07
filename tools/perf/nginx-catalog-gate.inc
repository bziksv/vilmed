# >>> VILMED catalog gate (до PHP, 2026-11-07)
# Включается в server { } vilmed.ru (vilmed.ru.includes).
# Закрываем только перебор: в адресе каталога section_id три раза и больше.
# Обычный поиск /catalog/?q=..., сортировка и страницы открываются как раньше,
# в том числе в инкогнито без куки и без referer.
# IP Google/Яндекса не режем. POST не режем.
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
# <<< VILMED catalog gate
