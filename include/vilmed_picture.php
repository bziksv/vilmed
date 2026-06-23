<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

if (!function_exists('vilmedPictureHtml')) {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/include/vilmed_perf.php';
}

/** @var array|null $picture */
/** @var string|null $class */
/** @var string|null $alt */
/** @var string|null $title */
/** @var array|null $attrs */

echo vilmedPictureHtml(
	is_array($picture ?? null) ? $picture : [],
	array_merge(
		[
			'class' => $class ?? 'item_img',
			'alt' => $alt ?? '',
			'title' => $title ?? '',
		],
		is_array($attrs ?? null) ? $attrs : []
	)
);
