<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

/**
 * Resize catalog preview for list/card templates (PSI: properly size images).
 */
function vilmedOptimizePicture($picture, int $width = 280, int $height = 280): array
{
	if (!is_array($picture) || empty($picture['ID'])) {
		if (is_array($picture) && !empty($picture['SRC'])) {
			return $picture;
		}
		return [
			'SRC' => SITE_TEMPLATE_PATH . '/images/no-photo.jpg',
			'WIDTH' => 150,
			'HEIGHT' => 150,
		];
	}

	if (
		(!empty($picture['WIDTH']) && (int)$picture['WIDTH'] <= $width)
		&& (!empty($picture['HEIGHT']) && (int)$picture['HEIGHT'] <= $height)
	) {
		return $picture;
	}

	$resize = CFile::ResizeImageGet(
		$picture['ID'],
		['width' => $width, 'height' => $height],
		BX_RESIZE_IMAGE_PROPORTIONAL,
		true
	);

	if (!$resize) {
		return $picture;
	}

	return [
		'SRC' => $resize['src'],
		'WIDTH' => (int)$resize['width'],
		'HEIGHT' => (int)$resize['height'],
	];
}
