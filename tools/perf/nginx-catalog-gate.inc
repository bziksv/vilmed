# >>> VILMED catalog gate (до PHP, 2026-11-07)
# Включается в server { } vilmed.ru (vilmed.ru.includes).
# Прямой GET/HEAD /catalog/ с q|sort|view|section_id|PAGEN без куки vm_cg
# и без перехода со страницы vilmed.ru закрывается здесь, PHP не стартует.
# Капча эти запросы не решает, а каждый из них занимал воркер Битрикса.
# POST (отправка кода), кука vm_cg, referer vilmed.ru, IP Google/Яндекса и свой прогрев — мимо.
# Чужой User-Agent «Googlebot» не пропускается: его уже подставляли и забирали выдачу.
# Три и больше section_id в одном адресе — это их перебор, не фильтр покупателя.
# Referer vilmed.ru и кука тут не помогают: и то и другое они уже подставляют.
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

set $vm_gate 0;
if ($request_uri ~* "^/catalog/.*[?&](q|sort|view|section_id|PAGEN)") {
    set $vm_gate 1;
}
if ($request_method !~ ^(GET|HEAD)$) {
    set $vm_gate 0;
}
if ($cookie_vm_cg ~ "^[0-9]{10}\.[0-9a-f]{64}$") {
    set $vm_gate 0;
}
if ($http_referer ~* "^https?://(www\.)?vilmed\.ru([/:?#]|$)") {
    set $vm_gate 0;
}
if ($http_user_agent ~* "(vilmed-warmup|vilmed-psi)") {
    set $vm_gate 0;
}
if ($search_engine = 1) {
    set $vm_gate 0;
}
if ($vm_gate = 1) {
    return 444;
}
# <<< VILMED catalog gate
