#!/usr/bin/env python3
"""Удаляет страницы композита, ссылающиеся на отсутствующие сборки /bitrix/cache/(css|js)/.

Такая страница отдаётся без стилей и скриптов; после удаления она соберётся заново.

    python3 tools/perf/prod-composite-drop-stale.py [ROOT] [--dry-run]
"""
import os
import re
import sys

args = [a for a in sys.argv[1:] if not a.startswith("--")]
dry = "--dry-run" in sys.argv
root = args[0] if args else "/var/www/vilmed_ru_usr/data/www/vilmed.ru"
pages = os.path.join(root, "bitrix/html_pages")
rx = re.compile(rb"/bitrix/cache/(?:css|js)/[^\"'?\s]+")

total = stale = 0
missing = set()
for d, _, files in os.walk(pages):
    for f in files:
        if not f.endswith(".html"):
            continue
        total += 1
        p = os.path.join(d, f)
        try:
            with open(p, "rb") as fh:
                refs = set(rx.findall(fh.read()))
        except OSError:
            continue
        gone = [r for r in refs if not os.path.exists(os.path.join(root, r.decode().lstrip("/")))]
        if gone:
            stale += 1
            missing.update(r.decode() for r in gone)
            if not dry:
                os.remove(p)

print(f"  composite pages: {total}, stale {'found' if dry else 'removed'}: {stale}, missing bundles: {len(missing)}")
