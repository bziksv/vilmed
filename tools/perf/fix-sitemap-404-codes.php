<?php
/**
 * Fix sitemap 404s:
 * 1) sections whose CODE ends with -r and have no counterpart without -r
 *    (htaccess strips -r/ → 404)
 * 2) element CODEs with spaces
 *
 * php tools/perf/fix-sitemap-404-codes.php [--dry]
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
$dry = in_array('--dry', $argv, true);

function vilmedTranslitCode(string $s): string
{
	$map = [
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
		'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
		'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
		'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
		'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
	];
	$s = mb_strtolower($s, 'UTF-8');
	$out = '';
	$len = mb_strlen($s, 'UTF-8');
	for ($i = 0; $i < $len; $i++) {
		$ch = mb_substr($s, $i, 1, 'UTF-8');
		if (isset($map[$ch])) {
			$out .= $map[$ch];
		} elseif (preg_match('/[a-z0-9]/', $ch)) {
			$out .= $ch;
		} else {
			$out .= '-';
		}
	}
	$out = preg_replace('/-+/', '-', $out) ?? $out;

	return trim($out, '-');
}

// --- sections ---
$res = $m->query("SELECT ID, CODE FROM b_iblock_section WHERE IBLOCK_ID = 24 AND CODE LIKE '%-r'");
$secN = 0;
while ($row = $res->fetch_assoc()) {
	$code = (string)$row['CODE'];
	if (substr($code, -2) !== '-r') {
		continue;
	}
	$base = substr($code, 0, -2);
	$st = $m->prepare('SELECT ID FROM b_iblock_section WHERE IBLOCK_ID = 24 AND CODE = ?');
	$st->bind_param('s', $base);
	$st->execute();
	if ($st->get_result()->fetch_assoc()) {
		continue; // duplicate pair — htaccess redirect is intentional
	}
	$id = (int)$row['ID'];
	if ($dry) {
		echo "DRY SEC {$id} {$code} -> {$base}\n";
		$secN++;
		continue;
	}
	$upd = $m->prepare('UPDATE b_iblock_section SET CODE = ? WHERE ID = ?');
	$upd->bind_param('si', $base, $id);
	echo ($upd->execute() ? 'OK' : 'FAIL') . " SEC {$id} {$code} -> {$base}\n";
	$secN++;
}
echo "sections_fixed={$secN}\n";

// --- elements with spaces in CODE ---
$res = $m->query("SELECT ID, CODE, NAME FROM b_iblock_element WHERE IBLOCK_ID = 24 AND CODE LIKE '% %'");
$elN = 0;
while ($row = $res->fetch_assoc()) {
	$id = (int)$row['ID'];
	$new = vilmedTranslitCode((string)($row['NAME'] !== '' ? $row['NAME'] : $row['CODE']));
	$base = $new;
	$i = 0;
	while (true) {
		$st = $m->prepare('SELECT ID FROM b_iblock_element WHERE IBLOCK_ID = 24 AND CODE = ? AND ID <> ?');
		$st->bind_param('si', $new, $id);
		$st->execute();
		if (!$st->get_result()->fetch_assoc()) {
			break;
		}
		$i++;
		$new = $base . '-' . $i;
	}
	if ($dry) {
		echo "DRY EL {$id} [{$row['CODE']}] -> {$new}\n";
		$elN++;
		continue;
	}
	$upd = $m->prepare('UPDATE b_iblock_element SET CODE = ? WHERE ID = ?');
	$upd->bind_param('si', $new, $id);
	echo ($upd->execute() ? 'OK' : 'FAIL') . " EL {$id} -> {$new}\n";
	$elN++;
}
echo "elements_fixed={$elN}\n";
