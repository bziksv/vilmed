#!/usr/bin/env python3
"""Проверка поисковых роботов для всех сайтов сервера (nginx geo-списки).

Источник: vilmed.ru/tools/server/vm-bot-verify.py → /usr/local/sbin/vm-bot-verify.py

  vm-bot-verify.py                   новые строки логов → DNS-проверка (cron, раз в минуту)
  vm-bot-verify.py --seed            все логи, включая архивы .gz
  vm-bot-verify.py --refresh-official  диапазоны Google и Bing (cron, раз в сутки)
  vm-bot-verify.py --refresh-country   Россия и соседи из DB-IP (cron, раз в месяц)

Робот подтверждён, если PTR оканчивается на домен поисковика и этот PTR
указывает обратно на тот же IP. PTR чужой или нет PTR — подделка.
Ошибка DNS (таймаут) — решения нет, адрес проверяется снова в следующий запуск.
"""
import concurrent.futures
import datetime
import fcntl
import glob
import gzip
import ipaddress
import json
import os
import re
import socket
import subprocess
import sys
import time
import urllib.request

STATE_DIR = '/var/lib/vm-bots'
OUT_DIR = '/etc/nginx/vm-bots'
CACHE = STATE_DIR + '/cache.json'
OFFSETS = STATE_DIR + '/offsets.json'
LOG = '/var/log/vm-bot-verify.log'
LOGS = '/var/www/*/data/logs/*-frontend.access.log'

CLAIMS = re.compile(
    r'(yandex\.com/bots|Googlebot|Google-InspectionTool|Storebot-Google|AdsBot-Google|GoogleOther'
    r'|Mediapartners-Google|APIs-Google|FeedFetcher-Google|bingbot|BingPreview|msnbot|adidxbot|Mail\.RU_Bot)',
    re.I)
OK_PTR = re.compile(
    r'\.(yandex\.ru|yandex\.net|yandex\.com|googlebot\.com|google\.com|googleusercontent\.com'
    r'|search\.msn\.com|mail\.ru)\.?$', re.I)
LINE = re.compile(r'^(\S+) .*"([^"]*)"\s*$')

VERIFIED_TTL = 30 * 86400
FAKE_TTL = 7 * 86400
OFFICIAL = {
    'google': [
        'https://developers.google.com/static/search/apis/ipranges/googlebot.json',
        'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json',
    ],
    'bing': ['https://www.bing.com/toolbox/bingbot.json'],
}
LOCAL_COUNTRIES = {'RU', 'BY', 'KZ', 'KG', 'AM', 'UZ', 'TJ', 'AZ'}
# Свои серверы и офис: подделкой не считаем, даже если пишут Googlebot в User-Agent.
OWN_NETS = [ipaddress.ip_network(n) for n in (
    '127.0.0.0/8', '217.28.220.186/32', '62.109.16.215/32', '185.43.7.44/32',
    '155.212.171.103/32', '217.25.226.46/32', '185.23.82.233/32',
)]
OWN_PTR = re.compile(r'(^|\.)prime-ltd\.su\.?$', re.I)


def is_own(ip):
    addr = ipaddress.ip_address(ip)
    return any(addr in n for n in OWN_NETS if n.version == addr.version)


def log(msg):
    line = '%s %s\n' % (datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'), msg)
    with open(LOG, 'a') as f:
        f.write(line)


def load_json(path, default):
    try:
        with open(path) as f:
            return json.load(f)
    except Exception:
        return default


def save_json(path, data):
    tmp = path + '.tmp'
    with open(tmp, 'w') as f:
        json.dump(data, f)
    os.replace(tmp, path)


def write_if_changed(path, text):
    try:
        with open(path) as f:
            if f.read() == text:
                return False
    except FileNotFoundError:
        pass
    tmp = path + '.tmp'
    with open(tmp, 'w') as f:
        f.write(text)
    os.replace(tmp, path)
    return True


def read_lines(seed):
    offsets = {} if seed else load_json(OFFSETS, {})
    new_offsets = {}
    for path in glob.glob(LOGS):
        try:
            st = os.stat(path)
        except FileNotFoundError:
            continue
        key = path
        prev = offsets.get(key)
        start = 0
        if prev and prev.get('ino') == st.st_ino and prev.get('pos', 0) <= st.st_size:
            start = prev['pos']
        with open(path, 'rb') as f:
            f.seek(start)
            for raw in f:
                yield raw.decode('utf-8', 'replace')
            new_offsets[key] = {'ino': st.st_ino, 'pos': f.tell()}
        if seed:
            for old in sorted(glob.glob(path + '.*')):
                opener = gzip.open if old.endswith('.gz') else open
                try:
                    with opener(old, 'rb') as f:
                        for raw in f:
                            yield raw.decode('utf-8', 'replace')
                except Exception:
                    continue
    save_json(OFFSETS, new_offsets)


def check_ip(ip):
    try:
        host = socket.gethostbyaddr(ip)[0]
    except socket.herror:
        return 'fake', ''
    except Exception:
        return 'unknown', ''
    if OWN_PTR.search(host):
        return 'own', host
    if not OK_PTR.search(host):
        return 'fake', host
    try:
        infos = socket.getaddrinfo(host, None)
    except socket.gaierror as e:
        if e.errno in (socket.EAI_NONAME, getattr(socket, 'EAI_NODATA', -5)):
            return 'fake', host
        return 'unknown', host
    except Exception:
        return 'unknown', host
    addrs = {i[4][0] for i in infos}
    return ('verified' if ip in addrs else 'fake'), host


def geo_line(ip):
    return '%s 1;\n' % ip


def render(cache):
    now = time.time()
    ver, fake = [], []
    for ip, rec in cache.items():
        if is_own(ip) or OWN_PTR.search(rec.get('h') or ''):
            continue
        if rec['s'] == 'verified' and now - rec['t'] < VERIFIED_TTL:
            ver.append(ip)
        elif rec['s'] == 'fake' and now - rec['t'] < FAKE_TTL:
            fake.append(ip)
    changed = write_if_changed(OUT_DIR + '/verified.geo', ''.join(geo_line(i) for i in sorted(ver)))
    changed |= write_if_changed(OUT_DIR + '/fake.geo', ''.join(geo_line(i) for i in sorted(fake)))
    return changed, len(ver), len(fake)


def reload_nginx(backups):
    t = subprocess.run(['nginx', '-t'], capture_output=True, text=True)
    if t.returncode != 0:
        for path, old in backups.items():
            if old is None:
                continue
            with open(path, 'w') as f:
                f.write(old)
        log('nginx -t FAILED, списки откатаны: %s' % t.stderr.strip().replace('\n', ' | ')[:400])
        return False
    subprocess.run(['systemctl', 'reload', 'nginx'], check=False)
    return True


def snapshot(names):
    out = {}
    for n in names:
        p = OUT_DIR + '/' + n
        try:
            with open(p) as f:
                out[p] = f.read()
        except FileNotFoundError:
            out[p] = None
    return out


def ensure_files():
    os.makedirs(STATE_DIR, exist_ok=True)
    os.makedirs(OUT_DIR, exist_ok=True)
    for n in ('verified.geo', 'fake.geo', 'official.geo', 'local-country.geo'):
        p = OUT_DIR + '/' + n
        if not os.path.exists(p):
            open(p, 'w').close()


def run_verify(seed):
    cache = load_json(CACHE, {})
    now = time.time()
    todo = set()
    for line in read_lines(seed):
        if not CLAIMS.search(line):
            continue
        m = LINE.match(line)
        if not m or not CLAIMS.search(m.group(2)):
            continue
        ip = m.group(1)
        try:
            if is_own(ip):
                continue
        except ValueError:
            continue
        rec = cache.get(ip)
        ttl = VERIFIED_TTL if rec and rec['s'] == 'verified' else FAKE_TTL
        if rec and rec['s'] != 'unknown' and now - rec['t'] < ttl:
            continue
        todo.add(ip)
    stats = {'verified': 0, 'fake': 0, 'unknown': 0, 'own': 0}
    if todo:
        with concurrent.futures.ThreadPoolExecutor(max_workers=16) as ex:
            for ip, (state, host) in zip(todo, ex.map(check_ip, todo)):
                stats[state] += 1
                prev = cache.get(ip, {})
                tries = prev.get('n', 0) + 1 if state == 'unknown' else 0
                cache[ip] = {'s': state, 't': now, 'h': host, 'n': tries}
                if state == 'fake':
                    log('fake %s ptr=%s' % (ip, host or '-'))
        save_json(CACHE, cache)
    backups = snapshot(['verified.geo', 'fake.geo'])
    changed, nv, nf = render(cache)
    if changed:
        reload_nginx(backups)
    if todo or changed:
        log('checked=%d verified=%d fake=%d unknown=%d | lists: verified=%d fake=%d reload=%s'
            % (len(todo), stats['verified'], stats['fake'], stats['unknown'], nv, nf, changed))


def fetch(url, timeout=60):
    req = urllib.request.Request(url, headers={'User-Agent': 'vm-bot-verify/1.0'})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read()


def run_official():
    lines = []
    failed = False
    for name, urls in OFFICIAL.items():
        got = 0
        for url in urls:
            data = None
            for attempt in range(3):
                try:
                    data = json.loads(fetch(url))
                    break
                except Exception as e:
                    log('official %s (попытка %d): %s' % (url, attempt + 1, e))
                    time.sleep(5)
            if data is None:
                failed = True
                continue
            for p in data.get('prefixes', []):
                net = p.get('ipv4Prefix') or p.get('ipv6Prefix')
                if net:
                    ipaddress.ip_network(net)
                    lines.append('%s 1;  # %s\n' % (net, name))
                    got += 1
        if got == 0:
            log('official %s: пусто, оставляю старый список' % name)
            return
    if failed and os.path.getsize(OUT_DIR + '/official.geo') > 0:
        log('official: не все списки скачались, оставляю старый')
        return
    backups = snapshot(['official.geo'])
    if write_if_changed(OUT_DIR + '/official.geo', ''.join(sorted(set(lines)))):
        reload_nginx(backups)
        log('official: %d диапазонов' % len(set(lines)))


def run_country():
    today = datetime.date.today()
    months = [today.strftime('%Y-%m'), (today.replace(day=1) - datetime.timedelta(days=1)).strftime('%Y-%m')]
    data = None
    for m in months:
        try:
            data = fetch('https://download.db-ip.com/free/dbip-country-lite-%s.csv.gz' % m, timeout=180)
            break
        except Exception as e:
            log('country %s: %s' % (m, e))
    if not data:
        return
    ranges = []
    for row in gzip.decompress(data).decode('utf-8', 'replace').splitlines():
        parts = row.split(',')
        if len(parts) < 3 or ':' in parts[0] or parts[2] not in LOCAL_COUNTRIES:
            continue
        ranges.append((int(ipaddress.IPv4Address(parts[0])), int(ipaddress.IPv4Address(parts[1]))))
    ranges.sort()
    merged = []
    for a, b in ranges:
        if merged and a <= merged[-1][1] + 1:
            merged[-1][1] = max(merged[-1][1], b)
        else:
            merged.append([a, b])
    if len(merged) < 1000:
        log('country: подозрительно мало диапазонов (%d), оставляю старый список' % len(merged))
        return
    text = ''.join('%s-%s 1;\n' % (ipaddress.IPv4Address(a), ipaddress.IPv4Address(b)) for a, b in merged)
    backups = snapshot(['local-country.geo'])
    if write_if_changed(OUT_DIR + '/local-country.geo', text):
        reload_nginx(backups)
        log('country: %d диапазонов (%s)' % (len(merged), ','.join(sorted(LOCAL_COUNTRIES))))


def main():
    ensure_files()
    lock = open(STATE_DIR + '/lock', 'w')
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        return
    socket.setdefaulttimeout(5)
    if '--refresh-official' in sys.argv:
        run_official()
    elif '--refresh-country' in sys.argv:
        run_country()
    else:
        run_verify('--seed' in sys.argv)


if __name__ == '__main__':
    main()
