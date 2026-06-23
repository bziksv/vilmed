# Perf-скрипты (vilmed.ru, prod)

## Обычный деплой (без сброса композита)

```bash
cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
bash tools/perf/prod-deploy.sh
```

## Полный цикл (deploy + checker + webp 2000)

```bash
bash tools/perf/prod-full-optimize.sh
WEBP_LIMIT=3000 bash tools/perf/prod-full-optimize.sh
```

## Полный сброс композита (редко — смена шаблона)

```bash
CLEAR_HTML_PAGES=1 bash tools/perf/prod-deploy.sh
```

## Отдельные команды

```bash
bash tools/perf/prod-warmup.sh https://vilmed.ru
bash tools/perf/prod-warmup-sitemap.sh https://vilmed.ru 25   # топ категорий из sitemap
bash tools/perf/prod-composite-check.sh https://vilmed.ru
bash tools/perf/prod-checker-fixes.sh
php tools/perf/webp-warmup.php --limit=1000
```

После `git pull` с PHP-кодом: `systemctl reload apache2` (в prod-deploy.sh уже есть).

Композит: `bitrix/html_pages/.enabled` + warmup → TTFB ~0.02s на сервере.
