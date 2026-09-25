<?php
/**
 * One-shot: fix broken DETAIL_TEXT on specific product pages (W3C orphan </p></li>, broken <h2>).
 * php tools/perf/fix-w3c-product-snippets.php
 */
$docRoot = dirname(__DIR__, 2);
$settings = include $docRoot . '/bitrix/.settings.php';
$db = $settings['connections']['value']['default'];
$m = new mysqli($db['host'], $db['login'], $db['password'], $db['database']);
if ($m->connect_error) {
	fwrite(STDERR, $m->connect_error . "\n");
	exit(1);
}
$m->set_charset('utf8');

$fixes = [
	'shchelevaya-lampa-lshch2-01-3-kh-pozitsionnaya-s-galogennym-istochnikom-sveta-orion-medik' => static function (string $t): string {
		$t = str_replace("\nПреимущества</p><ul>", "\n<p>Преимущества</p><ul>", $t);
		$t = str_replace('Преимущества</p><ul>', '<p>Преимущества</p><ul>', $t);
		$t = str_replace('лампу.м</ul></p>', 'лампу.</li></ul>', $t);
		$t = str_replace('лампу.м</ul>', 'лампу.</li></ul>', $t);
		$t = str_replace('</ul></p>', '</ul>', $t);
		return $t;
	},
	'apparat-ivl-zisline-jv100-a-rossiya' => static function (string $t): string {
		return str_replace('</ul></p>', '</ul>', $t);
	},
	'defibrillyator-monitor-beneheart-d6-mindray-kitay' => static function (string $t): string {
		return preg_replace_callback('#<ul>(.*?)</ul>#is', static function (array $m): string {
			$inner = $m[1];
			if (!preg_match('#^\s*</li>#u', $inner)) {
				return $m[0];
			}
			// </li>text → <li>text</li> for broken openers
			$inner = preg_replace_callback(
				'#</li>([^<]+?)(?=</li>|<li>|</ul>|$)#us',
				static function (array $mm): string {
					$text = rtrim($mm[1]);
					if ($text === '') {
						return '';
					}
					return '<li>' . $text . '</li>';
				},
				$inner
			) ?? $inner;
			$inner = preg_replace('#</li>\s*</li>#u', '</li>', $inner) ?? $inner;
			return '<ul>' . $inner . '</ul>';
		}, $t) ?? $t;
	},
	'sistema-ochistki-dykhatelnykh-putey-the-vest-pnevmovibratsionnaya-sistema-zhilet' => static function (string $t): string {
		$t = str_replace('<h2Принцип работы</h2>', '<h2>Принцип работы</h2>', $t);
		// generic: <h2Title</h2> → <h2>Title</h2>
		$t = preg_replace('#<h([1-6])([^>\s/][^<]*)</h\1>#u', '<h$1>$2</h$1>', $t) ?? $t;
		$t = preg_replace('/(["\'])\s*;\s+(?=[a-zA-Z_][\w:-]*=)/', '$1 ', $t) ?? $t;
		return $t;
	},
	'fetalnyy-monitor-serii-unicare-mcf-21-kitay' => static function (string $t): string {
		$t = str_replace('</ul></p>', '</ul>', $t);
		// "Заголовок</p><ul>" without opening <p>
		$t = preg_replace('#(^|[\n>])([А-ЯA-Z][^<\n]{1,80})</p>(\s*<ul)#u', '$1<p>$2</p>$3', $t) ?? $t;
		return $t;
	},
];

foreach ($fixes as $code => $fn) {
	$st = $m->prepare('SELECT ID, DETAIL_TEXT, PREVIEW_TEXT FROM b_iblock_element WHERE CODE=? AND IBLOCK_ID=24 LIMIT 1');
	$st->bind_param('s', $code);
	$st->execute();
	$row = $st->get_result()->fetch_assoc();
	if (!$row) {
		echo "MISS {$code}\n";
		continue;
	}
	$id = (int)$row['ID'];
	$old = (string)$row['DETAIL_TEXT'];
	$new = $fn($old);
	$prev = (string)$row['PREVIEW_TEXT'];
	$newPrev = str_replace('</ul></p>', '</ul>', $prev);

	if ($new === $old && $newPrev === $prev) {
		echo "NOCHANGE {$code} id={$id}\n";
		if (strpos($code, 'fetal') !== false) {
			echo "TAIL: " . substr($old, -500) . "\n";
			echo "HEAD: " . substr($old, 0, 500) . "\n";
			echo "PREVIEW: " . substr($prev, 0, 500) . "\n";
		}
		continue;
	}

	$upd = $m->prepare('UPDATE b_iblock_element SET DETAIL_TEXT=?, PREVIEW_TEXT=? WHERE ID=?');
	$upd->bind_param('ssi', $new, $newPrev, $id);
	if ($upd->execute()) {
		echo "OK {$code} id={$id}\n";
	} else {
		echo "FAIL {$code} " . $m->error . "\n";
	}
}

echo "done\n";
