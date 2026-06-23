# Perf-скрипты (vilmed.ru, prod)

Запуск с сервера:

```bash
cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
bash tools/perf/prod-warmup.sh https://vilmed.ru
bash tools/perf/prod-composite-check.sh https://vilmed.ru
bash tools/perf/prod-post-composite.sh
php tools/perf/webp-warmup.php --limit=1000
```

После `git pull` с PHP-кодом: `systemctl reload apache2` (opcache.validate_timestamps=0).

Композит: `bitrix/html_pages/.enabled` должен существовать; после warmup — десятки/сотни файлов в `bitrix/html_pages/`.
