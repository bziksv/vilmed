<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

if (!function_exists('vilmedResizePicture')) {
	/**
	 * Resize with fallback dimensions when PHP cannot read /upload/ locally
	 * or CFile::ResizeImageGet returns 0×0.
	 */
	function vilmedResizePicture($picture, int $width, int $height): ?array
	{
		if (is_numeric($picture)) {
			$picture = CFile::GetFileArray((int)$picture);
		}

		if (!is_array($picture) || empty($picture['SRC'])) {
			return null;
		}

		$picWidth = (int)($picture['WIDTH'] ?? 0);
		$picHeight = (int)($picture['HEIGHT'] ?? 0);

		if ($picWidth <= 0 || $picHeight <= 0 || $picWidth > $width || $picHeight > $height) {
			$resized = CFile::ResizeImageGet(
				$picture,
				['width' => $width, 'height' => $height],
				BX_RESIZE_IMAGE_PROPORTIONAL,
				true
			);
		} else {
			$resized = [
				'src' => $picture['SRC'],
				'width' => $picWidth,
				'height' => $picHeight,
			];
		}

		if (!is_array($resized) || empty($resized['src'])) {
			if (empty($picture['SRC'])) {
				return null;
			}

			$resized = [
				'src' => $picture['SRC'],
				'width' => 0,
				'height' => 0,
			];
		}

		if ((int)($resized['width'] ?? 0) <= 0 || (int)($resized['height'] ?? 0) <= 0) {
			$resized['width'] = $width;
			$resized['height'] = $height;
		}

		return [
			'SRC' => $resized['src'],
			'WIDTH' => $resized['width'],
			'HEIGHT' => $resized['height'],
		];
	}
}

if (!function_exists('vilmedOptimizePicture')) {
	/** Catalog list/card preview — target 280×280 for PSI «properly size images». */
	function vilmedOptimizePicture($picture, int $width = 280, int $height = 280): array
	{
		$fallback = [
			'SRC' => SITE_TEMPLATE_PATH . '/images/no-photo.jpg',
			'WIDTH' => 150,
			'HEIGHT' => 150,
		];

		if (!is_array($picture) || empty($picture['ID'])) {
			if (is_array($picture) && !empty($picture['SRC'])) {
				$optimized = vilmedResizePicture($picture, $width, $height);
				return $optimized ?? $picture;
			}

			return $fallback;
		}

		$optimized = vilmedResizePicture($picture, $width, $height);
		return $optimized ?? $fallback;
	}
}

if (!function_exists('vilmedBasketPicture')) {
	function vilmedBasketPicture($fileId): ?array
	{
		$fileId = (int)$fileId;
		if ($fileId <= 0) {
			return null;
		}

		$picture = vilmedResizePicture($fileId, 65, 65);
		if ($picture === null) {
			return null;
		}

		return [
			'src' => $picture['SRC'],
			'width' => $picture['WIDTH'],
			'height' => $picture['HEIGHT'],
		];
	}
}
