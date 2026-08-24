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

if (!function_exists('vilmedImagesAreDuplicate')) {
	function vilmedImagesAreDuplicate(string $pathA, string $pathB): bool
	{
		if ($pathA === $pathB) {
			return true;
		}

		if (!is_file($pathA) || !is_file($pathB)) {
			return false;
		}

		$md5A = @md5_file($pathA);
		$md5B = @md5_file($pathB);
		if ($md5A && $md5B && $md5A === $md5B) {
			return true;
		}

		$hashA = vilmedImagePerceptualHash($pathA);
		$hashB = vilmedImagePerceptualHash($pathB);
		if ($hashA && $hashB && vilmedImageHashesSimilar($hashA, $hashB)) {
			return true;
		}

		return vilmedImagesPixelSimilar($pathA, $pathB);
	}
}
