<?php

if (!function_exists('vilmedResolveUploadFilePath')) {
	/**
	 * Путь к файлу upload для хеширования. На локалке /upload/ часто проксируется с prod —
	 * тогда качаем во временный кеш, иначе is_file() падает и дедуп не работает.
	 */
	function vilmedResolveUploadFilePath(array $file): ?string
	{
		$arr = null;
		if (!empty($file['ID'])) {
			$arr = CFile::GetFileArray((int)$file['ID']);
		}

		$src = null;
		if (is_array($arr) && !empty($arr['SRC'])) {
			$src = $arr['SRC'];
		} elseif (!empty($file['SRC'])) {
			$src = $file['SRC'];
		} elseif (!empty($file['SUBDIR']) && !empty($file['FILE_NAME'])) {
			$src = '/upload/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];
		}

		if (!$src) {
			return null;
		}

		$local = $_SERVER['DOCUMENT_ROOT'] . $src;
		if (is_file($local)) {
			return $local;
		}

		static $cache = [];
		if (isset($cache[$src])) {
			return $cache[$src];
		}

		$tmpDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/.vilmed_hash_cache';
		if (!is_dir($tmpDir)) {
			@mkdir($tmpDir, 0755, true);
		}

		$tmp = $tmpDir . '/' . md5($src) . '-' . basename($src);
		if (!is_file($tmp)) {
			$ctx = stream_context_create([
				'http' => ['timeout' => 5],
				'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
			]);

			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$host = $_SERVER['HTTP_HOST'] ?? 'vilmed.ru';
			$blob = @file_get_contents($scheme . '://' . $host . $src, false, $ctx);

			if ($blob === false && stripos($host, 'vilmed.ru') === false) {
				$blob = @file_get_contents('https://vilmed.ru' . $src, false, $ctx);
			}

			if ($blob === false) {
				return null;
			}

			@file_put_contents($tmp, $blob);
		}

		$cache[$src] = $tmp;

		return $tmp;
	}
}

if (!function_exists('vilmedImagePerceptualHash')) {
	/** 64-bit dHash — визуально одинаковые JPEG с разным md5. */
	function vilmedImagePerceptualHash(string $path): ?string
	{
		if (!is_file($path) || !function_exists('imagecreatefromstring')) {
			return null;
		}

		$blob = @file_get_contents($path);
		if ($blob === false) {
			return null;
		}

		$img = @imagecreatefromstring($blob);
		if (!$img) {
			return null;
		}

		$thumb = imagecreatetruecolor(9, 8);
		if (!$thumb) {
			imagedestroy($img);
			return null;
		}

		imagecopyresampled($thumb, $img, 0, 0, 0, 0, 9, 8, imagesx($img), imagesy($img));
		imagedestroy($img);

		$hash = '';
		for ($y = 0; $y < 8; $y++) {
			for ($x = 0; $x < 8; $x++) {
				$left = imagecolorat($thumb, $x, $y);
				$right = imagecolorat($thumb, $x + 1, $y);
				$leftGray = (($left >> 16) & 0xFF) * 299 + (($left >> 8) & 0xFF) * 587 + ($left & 0xFF) * 114;
				$rightGray = (($right >> 16) & 0xFF) * 299 + (($right >> 8) & 0xFF) * 587 + ($right & 0xFF) * 114;
				$hash .= ($leftGray < $rightGray) ? '1' : '0';
			}
		}

		imagedestroy($thumb);

		return $hash;
	}
}

if (!function_exists('vilmedImageHashesSimilar')) {
	function vilmedImageHashesSimilar(?string $a, ?string $b, int $maxDistance = 2): bool
	{
		if ($a === null || $b === null || strlen($a) !== 64 || strlen($b) !== 64) {
			return false;
		}

		$distance = 0;
		for ($i = 0; $i < 64; $i++) {
			if ($a[$i] !== $b[$i]) {
				$distance++;
				if ($distance > $maxDistance) {
					return false;
				}
			}
		}

		return true;
	}
}

if (!function_exists('vilmedImagesPixelSimilar')) {
	function vilmedImagesPixelSimilar(string $pathA, string $pathB, int $size = 32, float $maxMeanDiff = 10.0): bool
	{
		if (!function_exists('imagecreatefromstring')) {
			return false;
		}

		$blobA = @file_get_contents($pathA);
		$blobB = @file_get_contents($pathB);
		if ($blobA === false || $blobB === false) {
			return false;
		}

		$imgA = @imagecreatefromstring($blobA);
		$imgB = @imagecreatefromstring($blobB);
		if (!$imgA || !$imgB) {
			if ($imgA) {
				imagedestroy($imgA);
			}
			if ($imgB) {
				imagedestroy($imgB);
			}
			return false;
		}

		$thumbA = imagecreatetruecolor($size, $size);
		$thumbB = imagecreatetruecolor($size, $size);
		if (!$thumbA || !$thumbB) {
			imagedestroy($imgA);
			imagedestroy($imgB);
			if ($thumbA) {
				imagedestroy($thumbA);
			}
			if ($thumbB) {
				imagedestroy($thumbB);
			}
			return false;
		}

		imagecopyresampled($thumbA, $imgA, 0, 0, 0, 0, $size, $size, imagesx($imgA), imagesy($imgA));
		imagecopyresampled($thumbB, $imgB, 0, 0, 0, 0, $size, $size, imagesx($imgB), imagesy($imgB));
		imagedestroy($imgA);
		imagedestroy($imgB);

		$sum = 0;
		$pixels = $size * $size;
		for ($y = 0; $y < $size; $y++) {
			for ($x = 0; $x < $size; $x++) {
				$colorA = imagecolorat($thumbA, $x, $y);
				$colorB = imagecolorat($thumbB, $x, $y);
				$sum += abs((($colorA >> 16) & 0xFF) - (($colorB >> 16) & 0xFF));
				$sum += abs((($colorA >> 8) & 0xFF) - (($colorB >> 8) & 0xFF));
				$sum += abs(($colorA & 0xFF) - ($colorB & 0xFF));
			}
		}

		imagedestroy($thumbA);
		imagedestroy($thumbB);

		return ($sum / ($pixels * 3)) <= $maxMeanDiff;
	}
}

if (!function_exists('vilmedImageFingerprint')) {
	/**
	 * md5 + dHash + 32×32 RGB за одно декодирование; кеш на диске по пути/размеру/mtime.
	 * Без кеша карточка с 18 фото сравнивала пары заново (~20 с CPU на каждый промах композита).
	 *
	 * @return array{md5:string,dhash:?string,thumb:?string}|null
	 */
	function vilmedImageFingerprint(string $path): ?array
	{
		static $mem = [];
		$st = @stat($path);
		if ($st === false) {
			return null;
		}
		$key = md5($path . '|' . $st['size'] . '|' . $st['mtime']);
		if (isset($mem[$key])) {
			return $mem[$key];
		}

		$dir = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/bitrix/cache/vilmed_img_fp/' . substr($key, 0, 2);
		$file = $dir . '/' . $key . '.json';
		if (is_file($file)) {
			$data = json_decode((string) @file_get_contents($file), true);
			if (is_array($data) && isset($data['md5'])) {
				$data['thumb'] = isset($data['thumb']) ? base64_decode($data['thumb']) : null;
				return $mem[$key] = $data;
			}
		}

		$md5 = (string) @md5_file($path);
		$dhash = null;
		$thumb = null;
		$img = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) @file_get_contents($path)) : false;
		if ($img) {
			$w = imagesx($img);
			$h = imagesy($img);
			$d = imagecreatetruecolor(9, 8);
			if ($d) {
				imagecopyresampled($d, $img, 0, 0, 0, 0, 9, 8, $w, $h);
				$dhash = '';
				for ($y = 0; $y < 8; $y++) {
					for ($x = 0; $x < 8; $x++) {
						$l = imagecolorat($d, $x, $y);
						$r = imagecolorat($d, $x + 1, $y);
						$lg = (($l >> 16) & 0xFF) * 299 + (($l >> 8) & 0xFF) * 587 + ($l & 0xFF) * 114;
						$rg = (($r >> 16) & 0xFF) * 299 + (($r >> 8) & 0xFF) * 587 + ($r & 0xFF) * 114;
						$dhash .= ($lg < $rg) ? '1' : '0';
					}
				}
				imagedestroy($d);
			}
			$t = imagecreatetruecolor(32, 32);
			if ($t) {
				imagecopyresampled($t, $img, 0, 0, 0, 0, 32, 32, $w, $h);
				$thumb = '';
				for ($y = 0; $y < 32; $y++) {
					for ($x = 0; $x < 32; $x++) {
						$c = imagecolorat($t, $x, $y);
						$thumb .= chr(($c >> 16) & 0xFF) . chr(($c >> 8) & 0xFF) . chr($c & 0xFF);
					}
				}
				imagedestroy($t);
			}
			imagedestroy($img);
		}

		$data = ['md5' => $md5, 'dhash' => $dhash, 'thumb' => $thumb];
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		@file_put_contents($file, json_encode([
			'md5' => $md5,
			'dhash' => $dhash,
			'thumb' => $thumb !== null ? base64_encode($thumb) : null,
		]), LOCK_EX);

		return $mem[$key] = $data;
	}
}

if (!function_exists('vilmedImagesAreDuplicate')) {
	function vilmedImagesAreDuplicate(string $pathA, string $pathB): bool
	{
		if ($pathA === $pathB) {
			return true;
		}

		if (!is_file($pathA) || !is_file($pathB)) {
			return false;
		}

		$a = vilmedImageFingerprint($pathA);
		$b = vilmedImageFingerprint($pathB);
		if ($a === null || $b === null) {
			return false;
		}
		if ($a['md5'] !== '' && $a['md5'] === $b['md5']) {
			return true;
		}
		if ($a['dhash'] && $b['dhash'] && vilmedImageHashesSimilar($a['dhash'], $b['dhash'])) {
			return true;
		}
		if ($a['thumb'] === null || $b['thumb'] === null || strlen($a['thumb']) !== strlen($b['thumb'])) {
			return false;
		}
		$sum = 0;
		$len = strlen($a['thumb']);
		for ($i = 0; $i < $len; $i++) {
			$sum += abs(ord($a['thumb'][$i]) - ord($b['thumb'][$i]));
		}

		return ($sum / $len) <= 10.0;
	}
}
