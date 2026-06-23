<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

if (!function_exists('vilmedEnsureWebpSrc')) {
	/**
	 * Create/read cached .webp sibling next to jpg/png under document root.
	 */
	function vilmedEnsureWebpSrc(string $relativeSrc): ?string
	{
		if (!function_exists('imagewebp')) {
			return null;
		}

		$relativeSrc = (string)preg_replace('#\?.*$#', '', $relativeSrc);
		if ($relativeSrc === '' || $relativeSrc[0] !== '/') {
			return null;
		}

		$ext = strtolower(pathinfo($relativeSrc, PATHINFO_EXTENSION));
		if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
			return null;
		}

		$docRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');
		$sourcePath = $docRoot . $relativeSrc;
		if (!is_file($sourcePath) || !is_readable($sourcePath)) {
			return null;
		}

		$webpRelative = (string)preg_replace('/\.(jpe?g|png)$/i', '.webp', $relativeSrc);
		$webpPath = $docRoot . $webpRelative;

		if (is_file($webpPath) && filemtime($webpPath) >= filemtime($sourcePath)) {
			return $webpRelative;
		}

		// Do not generate WebP during HTTP — blocks TTFB on catalog (many images per page).
		// Run: php tools/perf/webp-warmup.php --limit=1000 (on server, after deploy).
		return null;
	}
}

if (!function_exists('vilmedGenerateWebpSrc')) {
	/** CLI / warmup only — writes .webp next to jpg/png. */
	function vilmedGenerateWebpSrc(string $relativeSrc): ?string
	{
		if (!function_exists('imagewebp')) {
			return null;
		}

		$relativeSrc = (string)preg_replace('#\?.*$#', '', $relativeSrc);
		if ($relativeSrc === '' || $relativeSrc[0] !== '/') {
			return null;
		}

		$ext = strtolower(pathinfo($relativeSrc, PATHINFO_EXTENSION));
		if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
			return null;
		}

		$docRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');
		$sourcePath = $docRoot . $relativeSrc;
		if (!is_file($sourcePath) || !is_readable($sourcePath)) {
			return null;
		}

		$webpRelative = (string)preg_replace('/\.(jpe?g|png)$/i', '.webp', $relativeSrc);
		$webpPath = $docRoot . $webpRelative;

		if (is_file($webpPath) && filemtime($webpPath) >= filemtime($sourcePath)) {
			return $webpRelative;
		}

		$webpDir = dirname($webpPath);
		if (!is_dir($webpDir) && !@mkdir($webpDir, 0755, true) && !is_dir($webpDir)) {
			return null;
		}

		$image = null;
		if (in_array($ext, ['jpg', 'jpeg'], true)) {
			$image = @imagecreatefromjpeg($sourcePath);
		} elseif ($ext === 'png') {
			$image = @imagecreatefrompng($sourcePath);
			if ($image !== false) {
				imagepalettetotruecolor($image);
				imagealphablending($image, true);
				imagesavealpha($image, true);
			}
		}

		if ($image === false || $image === null) {
			return null;
		}

		$saved = imagewebp($image, $webpPath, 82);
		imagedestroy($image);

		if (!$saved) {
			@unlink($webpPath);
			return null;
		}

		@chmod($webpPath, 0644);

		return $webpRelative;
	}
}

if (!function_exists('vilmedAttachWebp')) {
	function vilmedAttachWebp(array $picture): array
	{
		$src = $picture['SRC'] ?? '';
		if ($src === '') {
			return $picture;
		}

		$webpSrc = vilmedEnsureWebpSrc($src);
		if ($webpSrc !== null) {
			$picture['SRC_WEBP'] = $webpSrc;
		}

		return $picture;
	}
}

if (!function_exists('vilmedPicturePreloadSrc')) {
	function vilmedPicturePreloadSrc(array $picture): string
	{
		if (!empty($picture['SRC_WEBP'])) {
			return (string)$picture['SRC_WEBP'];
		}

		return (string)($picture['SRC'] ?? '');
	}
}

if (!function_exists('vilmedPictureHtml')) {
	function vilmedPictureHtml(array $picture, array $attrs = []): string
	{
		$src = $picture['SRC'] ?? '';
		if ($src === '') {
			return '';
		}

		$class = (string)($attrs['class'] ?? '');
		$alt = htmlspecialcharsbx((string)($attrs['alt'] ?? ''), ENT_QUOTES);
		$title = htmlspecialcharsbx((string)($attrs['title'] ?? ''), ENT_QUOTES);
		$width = (int)($picture['WIDTH'] ?? 0);
		$height = (int)($picture['HEIGHT'] ?? 0);
		$extra = '';

		foreach (['loading', 'fetchpriority', 'decoding'] as $key) {
			if (!empty($attrs[$key])) {
				$extra .= ' ' . $key . '="' . htmlspecialcharsbx((string)$attrs[$key], ENT_QUOTES) . '"';
			}
		}

		$widthAttr = $width > 0 ? ' width="' . $width . '"' : '';
		$heightAttr = $height > 0 ? ' height="' . $height . '"' : '';
		$classAttr = $class !== '' ? ' class="' . htmlspecialcharsbx($class, ENT_QUOTES) . '"' : '';
		$titleAttr = $title !== '' ? ' title="' . $title . '"' : '';
		$webpSrc = $picture['SRC_WEBP'] ?? '';

		if ($webpSrc !== '') {
			return '<picture>'
				. '<source srcset="' . htmlspecialcharsbx($webpSrc, ENT_QUOTES) . '" type="image/webp">'
				. '<img' . $classAttr . ' src="' . htmlspecialcharsbx($src, ENT_QUOTES) . '"' . $widthAttr . $heightAttr
				. ' alt="' . $alt . '"' . $titleAttr . $extra . ' />'
				. '</picture>';
		}

		return '<img' . $classAttr . ' src="' . htmlspecialcharsbx($src, ENT_QUOTES) . '"' . $widthAttr . $heightAttr
			. ' alt="' . $alt . '"' . $titleAttr . $extra . ' />';
	}
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

		return vilmedAttachWebp([
			'SRC' => $resized['src'],
			'WIDTH' => $resized['width'],
			'HEIGHT' => $resized['height'],
		]);
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

if (!function_exists('vilmedSetLcpPreload')) {
	/** Queue LCP image preload — injected into <head> via OnEndBufferContent. */
	function vilmedSetLcpPreload(string $src): void
	{
		$src = (string)preg_replace('#\?.*$#', '', $src);
		if ($src === '') {
			return;
		}
		$GLOBALS['vilmedLcpPreloadSrc'] = $src;
	}
}

if (!function_exists('vilmedInjectLcpPreload')) {
	function vilmedInjectLcpPreload(string &$content): void
	{
		$src = $GLOBALS['vilmedLcpPreloadSrc'] ?? '';
		if ($src === '' || stripos($content, 'rel="preload" as="image"') !== false) {
			return;
		}

		$link = '<link rel="preload" as="image" href="' . htmlspecialcharsbx($src, ENT_QUOTES) . '" fetchpriority="high">';
		if (preg_match('/<head\b[^>]*>/i', $content)) {
			$content = preg_replace('/<head\b[^>]*>/i', '$0' . $link, $content, 1);
		}
	}
}

AddEventHandler('main', 'OnEndBufferContent', 'vilmedInjectLcpPreload');
