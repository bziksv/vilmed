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

if (!function_exists('vilmedBestImageSrc')) {
	/** Prefer cached .webp sibling for img/background URLs (no generation on HTTP). */
	function vilmedBestImageSrc(string $src): string
	{
		$src = (string)preg_replace('#\?.*$#', '', $src);
		if ($src === '') {
			return $src;
		}

		$webpSrc = vilmedEnsureWebpSrc($src);
		return $webpSrc ?? $src;
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

if (!function_exists('vilmedPictureMaxSide')) {
	function vilmedPictureMaxSide($picture): int
	{
		if (!is_array($picture) || empty($picture['SRC'])) {
			return 0;
		}

		return max((int)($picture['WIDTH'] ?? 0), (int)($picture['HEIGHT'] ?? 0));
	}
}

if (!function_exists('vilmedPickLargerPicture')) {
	/**
	 * Prefer DETAIL when PREVIEW is a tiny thumbnail (common: 178×178 preview + 700×700 detail).
	 */
	function vilmedPickLargerPicture($preview, $detail = null): ?array
	{
		$previewOk = is_array($preview) && !empty($preview['SRC']);
		$detailOk = is_array($detail) && !empty($detail['SRC']);

		if (!$previewOk && !$detailOk) {
			return null;
		}
		if (!$previewOk) {
			return $detail;
		}
		if (!$detailOk) {
			return $preview;
		}

		return vilmedPictureMaxSide($detail) > vilmedPictureMaxSide($preview) ? $detail : $preview;
	}
}

if (!function_exists('vilmedOptimizePicture')) {
	/** Catalog list/card preview — 360×360 covers 178 CSS @2x retina. */
	function vilmedOptimizePicture($picture, int $width = 360, int $height = 360): array
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

if (!function_exists('vilmedResolveLcpPreloadSrc')) {
	/**
	 * Preload href must match a URL present in HTML (resize_cache webp on product detail,
	 * not the full-size /upload/iblock/ original queued from component_epilog).
	 */
	function vilmedResolveLcpPreloadSrc(string $queuedSrc, string $content): string
	{
		$queuedSrc = (string)preg_replace('#\?.*$#', '', $queuedSrc);
		if ($queuedSrc !== '' && stripos($content, $queuedSrc) !== false) {
			return $queuedSrc;
		}

		if (stripos($content, 'catalog-detail-picture') !== false) {
			if (preg_match(
				'#class="catalog-detail-picture"[^>]*>.*?<source[^>]+srcset="([^"]+)"[^>]*type="image/webp"#is',
				$content,
				$m
			)) {
				$src = html_entity_decode($m[1], ENT_QUOTES);
				if ($src !== '') {
					return $src;
				}
			}
			if (preg_match(
				'#class="catalog-detail-picture"[^>]*>.*?<img[^>]+src="([^"]+)"#is',
				$content,
				$m
			)) {
				$src = html_entity_decode($m[1], ENT_QUOTES);
				if ($src !== '' && stripos($src, 'data:image') === false) {
					return $src;
				}
			}
		}

		if (preg_match('#<picture>\s*<source[^>]+srcset="([^"]+\.webp)"#i', $content, $m)) {
			$src = html_entity_decode($m[1], ENT_QUOTES);
			if ($src !== '') {
				return $src;
			}
		}

		return '';
	}
}

if (!function_exists('vilmedInjectLcpPreload')) {
	function vilmedInjectLcpPreload(string &$content): void
	{
		$queuedSrc = $GLOBALS['vilmedLcpPreloadSrc'] ?? '';
		if ($queuedSrc === '' || stripos($content, 'rel="preload" as="image"') !== false) {
			return;
		}

		$src = vilmedResolveLcpPreloadSrc((string)$queuedSrc, $content);
		if ($src === '') {
			return;
		}

		$type = '';
		if (preg_match('/\.webp$/i', $src)) {
			$type = ' type="image/webp"';
		} elseif (function_exists('vilmedEnsureWebpSrc') && preg_match('/\.(?:png|jpe?g)$/i', $src)) {
			$webp = vilmedEnsureWebpSrc($src);
			if ($webp !== null && $webp !== '' && stripos($content, $webp) !== false) {
				$src = $webp;
				$type = ' type="image/webp"';
			}
		}

		if (stripos($content, $src) === false) {
			return;
		}

		$link = '<link rel="preload" as="image" href="' . htmlspecialcharsbx($src, ENT_QUOTES) . '"' . $type . ' fetchpriority="high">';
		if (preg_match('/<head\b[^>]*>/i', $content)) {
			$content = preg_replace('/<head\b[^>]*>/i', '$0' . $link, $content, 1);
		}
	}
}

if (!function_exists('vilmedInjectWebpImages')) {
	/** Wrap <img> in <picture> when .webp exists; skip already-wrapped and tiny vendor logos. */
	function vilmedInjectWebpImages(string &$content): void
	{
		if (stripos($content, '<img') === false) {
			return;
		}

		// Collapse duplicate nested <picture> from template + buffer.
		$prev = '';
		while ($prev !== $content) {
			$prev = $content;
			$content = preg_replace(
				'/(<picture>\s*<source[^>]+>\s*)<picture>\s*<source[^>]+>\s*(<img\b[^>]+>)\s*<\/picture>\s*<\/picture>/i',
				'$1$2</picture>',
				$content
			);
		}

		$offset = 0;
		while (preg_match('/<img\b([^>]*\ssrc="(\/[^"?]+\.(?:png|jpe?g))"[^>]*)>/i', $content, $m, PREG_OFFSET_CAPTURE, $offset)) {
			$fullMatch = $m[0][0];
			$pos = (int)$m[0][1];
			$attrs = $m[1][0];
			$src = $m[2][0];
			$nextOffset = $pos + strlen($fullMatch);

			$before = substr($content, max(0, $pos - 300), min(300, $pos));
			if (preg_match('/<picture\b[^>]*>\s*(?:<source[^>]*>\s*)?$/i', $before)) {
				$offset = $nextOffset;
				continue;
			}

			if (preg_match('/\bclass="[^"]*\bno-webp\b/i', $attrs)) {
				$offset = $nextOffset;
				continue;
			}

			$webp = vilmedEnsureWebpSrc($src);
			if ($webp === null) {
				$offset = $nextOffset;
				continue;
			}

			// Vendor logos and other tiny thumbs: one request, src=.webp (no <picture>).
			if (preg_match('#/resize_cache/iblock/[^/]+/69_24_1/#', $src)
				|| preg_match('#/upload/resize_cache/[^/]+/\d+_\d+_1/#', $src)) {
				$replacement = str_replace(
					'src="' . $src . '"',
					'src="' . $webp . '"',
					$fullMatch
				);
			} else {
				// width:% на img ломается внутри <picture> (flex/% от родителя) — переносим на picture
				$imgTag = $fullMatch;
				$pictureAttrs = '';
				if (preg_match('/\sstyle="([^"]*)"/i', $attrs, $sm)) {
					$style = $sm[1];
					$moved = [];
					$remain = $style;
					if (preg_match_all('/(?:^|;)\s*((?:max-)?width)\s*:\s*([^;]+)/i', $style, $pm, PREG_SET_ORDER)) {
						foreach ($pm as $p) {
							$prop = strtolower(trim($p[1]));
							$val = trim($p[2]);
							if (strpos($val, '%') === false) {
								continue;
							}
							$moved[] = $prop . ':' . $val;
							$remain = preg_replace(
								'/(?:^|;)\s*' . preg_quote($p[1], '/') . '\s*:\s*' . preg_quote($p[2], '/') . '\s*/i',
								';',
								$remain
							);
						}
					}
					if ($moved) {
						$pictureAttrs = ' style="' . htmlspecialcharsbx(implode(';', $moved), ENT_QUOTES) . ';display:inline-block"';
						$remain = trim(preg_replace('/;+/', ';', $remain), "; \t");
						$remain = ($remain === '' ? '' : $remain . ';') . 'width:100%;height:auto';
						$imgTag = preg_replace(
							'/\sstyle="[^"]*"/i',
							' style="' . htmlspecialcharsbx($remain, ENT_QUOTES) . '"',
							$fullMatch,
							1
						) ?? $fullMatch;
					}
				}
				$replacement = '<picture' . $pictureAttrs . '><source srcset="' . htmlspecialcharsbx($webp, ENT_QUOTES) . '" type="image/webp">'
					. $imgTag . '</picture>';
			}

			$content = substr_replace($content, $replacement, $pos, strlen($fullMatch));
			$offset = $pos + strlen($replacement);
		}
	}
}

if (!function_exists('vilmedWebpOnAfterFileSave')) {
	/** Auto-generate .webp when Bitrix saves jpg/png to /upload. */
	function vilmedWebpOnAfterFileSave(array $arFile): void
	{
		$src = (string)($arFile['SRC'] ?? '');
		if ($src === '' || $src[0] !== '/') {
			return;
		}

		vilmedGenerateWebpSrc($src);
	}
}

if (!function_exists('vilmedInjectLazyImages')) {
	/** Below-the-fold images — skip logo (no-lazy / fetchpriority=high). */
	function vilmedInjectLazyImages(string &$content): void
	{
		if (stripos($content, '<img') === false) {
			return;
		}

		$content = preg_replace_callback(
			'/<img\b(?![^>]*\bloading\s*=)([^>]*?)>/i',
			static function (array $m): string {
				$attrs = $m[1];
				if (preg_match('/\bclass="[^"]*\bno-lazy\b/i', $attrs)) {
					return $m[0];
				}
				if (stripos($attrs, 'fetchpriority="high"') !== false) {
					return $m[0];
				}

				return '<img loading="lazy"' . $attrs . '>';
			},
			$content
		);
	}
}

if (!function_exists('vilmedInjectBackgroundWebp')) {
	/** Swap background-image png/jpg URLs to .webp when pre-generated on disk. */
	function vilmedInjectBackgroundWebp(string &$content): void
	{
		$content = preg_replace_callback(
			'/(background(?:-image)?\s*:\s*[^;]*url\s*\(\s*(["\']?)(\/[^"\')\s>]+\.(?:png|jpe?g))\1\s*\))/i',
			static function (array $m): string {
				$webp = vilmedEnsureWebpSrc($m[2]);
				if ($webp === null) {
					return $m[0];
				}

				return str_replace($m[2], $webp, $m[0]);
			},
			$content
		);
	}
}

if (!function_exists('vilmedInjectFancyboxWebpHrefs')) {
	/**
	 * Product gallery lightbox: href="/upload/….jpg" → .webp when sibling exists.
	 * Fancybox loads href into <img>; modern browsers show WebP.
	 */
	function vilmedInjectFancyboxWebpHrefs(string &$content): void
	{
		if ($content === '') {
			return;
		}
		if (stripos($content, 'fancybox') === false && stripos($content, 'catalog-detail-images') === false) {
			return;
		}

		$content = preg_replace_callback(
			'#<a\b([^>]*\b(?:class|rel)=[^>]*\b(?:fancybox|lightbox|catalog-detail-images)\b[^>]*)>#i',
			static function (array $m): string {
				$tag = $m[0];
				if (!preg_match('/\bhref="(\/upload\/[^"?]+\.(?:jpe?g|png))"/i', $tag, $hm)) {
					return $tag;
				}
				$webp = vilmedEnsureWebpSrc($hm[1]);
				if ($webp === null) {
					return $tag;
				}

				return str_replace($hm[0], 'href="' . htmlspecialcharsbx($webp, ENT_QUOTES) . '"', $tag);
			},
			$content
		) ?? $content;
	}
}

if (!function_exists('vilmedIsMobileClient')) {
	function vilmedIsMobileClient(): bool
	{
		if (isset($GLOBALS['vilmedIsMobile'])) {
			return (bool)$GLOBALS['vilmedIsMobile'];
		}

		$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
		$GLOBALS['vilmedIsMobile'] = (bool)preg_match(
			'/Mobile|Android|iPhone|iPod|Opera Mini|IEMobile|webOS|BlackBerry/i',
			$ua
		);

		return $GLOBALS['vilmedIsMobile'];
	}
}

if (!function_exists('vilmedInjectCriticalHomeCss')) {
	/** Reserve layout before deferred template_*_v1.css loads (CLS on desktop). */
	function vilmedInjectCriticalHomeCss(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsHome'])) {
			return;
		}
		if (stripos($content, 'id="vilmed-critical"') !== false) {
			return;
		}

		$critical = '<style id="vilmed-critical">'
			. 'html,body,.body,.page-wrapper{width:100%;margin:0;padding:0}'
			. '.center{width:1234px;display:table;margin:0 auto}'
			. 'header{width:100%;min-height:107px;padding:10px 0}'
			. 'header .center{height:107px}'
			. '.header_1,.header_2,.header_3,.header_4{display:table-cell;vertical-align:middle}'
			. '.top-catalog{width:100%;height:40px;float:left;box-sizing:border-box}'
			. '.top_panel{width:100%;height:56px;display:none;margin:0;padding:0}'
			. '.content-wrapper{width:100%;padding:0 0 20px}'
			. '.content{width:1185px;float:left;margin:0 0 0 24px}'
			. '.left-column{width:203px;float:left;margin:0 24px 0 0}'
			. '.clr{clear:both}'
			. '.anythingContainer_DEFAULT{aspect-ratio:958/304}'
			. '.anythingContainer_16_9{aspect-ratio:958/538}'
			. '.anythingContainer_16_7{aspect-ratio:958/419}'
			. 'body.bg-fixed{background-attachment:scroll}'
			. '</style>';

		if (preg_match('/<head\b[^>]*>/i', $content)) {
			$content = preg_replace('/<head\b[^>]*>/i', '$0' . $critical, $content, 1);
		}
	}
}

if (!function_exists('vilmedResequenceCoreScripts')) {
	/** core_frame_cache requires BX.localStorage from core_ls — load order matters. */
	function vilmedResequenceCoreScripts(string &$content): void
	{
		if (!preg_match('#<script(\s[^>]*?\ssrc="([^"]*core_ls\.min\.js[^"]*)"[^>]*)>\s*</script>#i', $content, $lsMatch)) {
			return;
		}
		if (!preg_match('#<script(\s[^>]*?\ssrc="([^"]*core_frame_cache\.min\.js[^"]*)"[^>]*)>\s*</script>#i', $content, $fcMatch)) {
			return;
		}

		$lsTag = $lsMatch[0];
		$fcTag = $fcMatch[0];
		$lsPos = strpos($content, $lsTag);
		$fcPos = strpos($content, $fcTag);

		if ($lsPos === false || $fcPos === false || $lsPos < $fcPos) {
			return;
		}

		$content = str_replace($lsTag, '', $content);
		$content = str_replace($fcTag, $lsTag . $fcTag, $content);
	}
}

if (!function_exists('vilmedMaskNoscriptBlocks')) {
	/** Prevent defer-css regex from re-processing <link> inside <noscript> (double-wrap bug). */
	function vilmedMaskNoscriptBlocks(string $content, array &$placeholders): string
	{
		return preg_replace_callback(
			'/<noscript\b[^>]*>.*?<\/noscript>/is',
			static function (array $m) use (&$placeholders): string {
				$key = '%%VMD_NS' . count($placeholders) . '%%';
				$placeholders[$key] = $m[0];

				return $key;
			},
			$content
		);
	}
}

if (!function_exists('vilmedUnmaskPlaceholders')) {
	function vilmedUnmaskPlaceholders(string $content, array $placeholders): string
	{
		return $placeholders === [] ? $content : str_replace(array_keys($placeholders), array_values($placeholders), $content);
	}
}

if (!function_exists('vilmedDeferStylesheetLinks')) {
	function vilmedDeferStylesheetLinks(string &$content, array $patterns): void
	{
		if ($patterns === []) {
			return;
		}

		$placeholders = [];
		$content = vilmedMaskNoscriptBlocks($content, $placeholders);

		$content = preg_replace_callback(
			'/<link(\s[^>]+)>/i',
			static function (array $m) use ($patterns): string {
				if (!preg_match('#\brel=["\']stylesheet["\']#i', $m[1])) {
					return $m[0];
				}
				if (stripos($m[1], 'onload=') !== false || preg_match('#\bas=["\']style["\']#i', $m[1])) {
					return $m[0];
				}
				if (!preg_match('#\bhref=["\']([^"\']+)["\']#i', $m[1], $hrefMatch)) {
					return $m[0];
				}

				foreach ($patterns as $pattern) {
					if (preg_match('#' . $pattern . '#i', $hrefMatch[1])) {
						$href = htmlspecialcharsbx($hrefMatch[1], ENT_QUOTES);

						return '<link rel="preload" as="style" href="' . $href . '" onload="this.onload=null;this.rel=\'stylesheet\'">'
							. '<noscript><link rel="stylesheet" href="' . $href . '"></noscript>';
					}
				}

				return $m[0];
			},
			$content
		);

		$content = vilmedUnmaskPlaceholders($content, $placeholders);
	}
}

if (!function_exists('vilmedDeferHomeStylesheets')) {
	/** Homepage: defer non-critical CSS; keep Bitrix template bundle CSS blocking for CLS. */
	function vilmedDeferHomeStylesheets(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsHome'])) {
			return;
		}

		$patterns = [
			'ui\\.font\\.opensans',
			'font-awesome',
			'custom-forms',
			'slider\\.css',
			'fancybox',
			'slick\\.css',
			'template_styles\\.css',
			'colors\\.css',
			'schemes/',
		];

		vilmedDeferStylesheetLinks($content, $patterns);
	}
}

if (!function_exists('vilmedFixFontDisplay')) {
	function vilmedFixFontDisplay(string &$content): void
	{
		if (stripos($content, '@font-face') === false) {
			return;
		}

		$content = preg_replace_callback(
			'/@font-face\s*\{([^}]*)\}/i',
			static function (array $m): string {
				if (stripos($m[1], 'font-display') !== false) {
					return $m[0];
				}

				return '@font-face{' . rtrim($m[1], ';') . ';font-display:swap}';
			},
			$content
		);
	}
}

if (!function_exists('vilmedDeferPublicScripts')) {
	/** Apply defer attribute to matching external script tags. */
	function vilmedDeferPublicScripts(string &$content, array $deferNeedles, array $neverDeferNeedles): void
	{
		$content = preg_replace_callback(
			'/<script(\s[^>]*?\ssrc="([^"]+)"[^>]*)>\s*<\/script>/i',
			static function (array $m) use ($deferNeedles, $neverDeferNeedles): string {
				if (stripos($m[1], ' defer') !== false || stripos($m[1], ' async') !== false) {
					return $m[0];
				}
				foreach ($neverDeferNeedles as $needle) {
					if (stripos($m[2], $needle) !== false) {
						return $m[0];
					}
				}
				foreach ($deferNeedles as $needle) {
					$matched = (strpos($needle, '.+') !== false || strpos($needle, '\\') !== false)
						? preg_match('#' . $needle . '#i', $m[2])
						: stripos($m[2], $needle) !== false;
					if ($matched) {
						return '<script' . $m[1] . ' defer></script>';
					}
				}

				return $m[0];
			},
			$content
		);
	}
}

if (!function_exists('vilmedDeferHomeScripts')) {
	/** Homepage: defer non-critical JS (desktop + mobile TBT). */
	function vilmedDeferHomeScripts(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsHome'])) {
			return;
		}

		$neverDeferNeedles = [
			'core_frame_cache',
			'core_ls.min.js',
			'pull.client',
			'pull/protobuf',
			'rest.client',
			'dexie.bitrix',
			'sale.basket.basket.line',
		];

		$deferNeedles = [
			'socialservices/ss.js',
			'TweenMax.min.js',
		];

		vilmedDeferPublicScripts($content, $deferNeedles, $neverDeferNeedles);
	}
}

if (!function_exists('vilmedDeferCatalogScripts')) {
	/**
	 * Catalog/product: defer only scripts without inline init on the same page.
	 * Do not use broad needles like /script.js — they match basket.line and catalog.element.
	 */
	function vilmedDeferCatalogScripts(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsCatalogLike'])) {
			return;
		}

		$neverDeferNeedles = [
			'core_frame_cache',
			'core_ls.min.js',
			'sale.basket.basket.line',
			'search.title',
			'fancybox',
			'catalog.element',
			'geolocation',
		];

		$deferNeedles = [
			'socialservices/ss.js',
			'TweenMax.min.js',
		];

		vilmedDeferPublicScripts($content, $deferNeedles, $neverDeferNeedles);
	}
}

if (!function_exists('vilmedIsStorefrontRequest')) {
	function vilmedIsStorefrontRequest(): bool
	{
		if (defined('ADMIN_SECTION') && ADMIN_SECTION) {
			return false;
		}
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
			return false;
		}

		$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
		if (strpos($uri, '/bitrix/admin') !== false || strpos($uri, '/bitrix/tools') !== false) {
			return false;
		}

		return true;
	}
}

if (!function_exists('vilmedIsCatalogLikeRequest')) {
	function vilmedIsCatalogLikeRequest(): bool
	{
		if (!empty($GLOBALS['vilmedIsCatalogLike'])) {
			return true;
		}
		if (defined('ADMIN_SECTION') && ADMIN_SECTION) {
			return false;
		}

		$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
		$siteDir = defined('SITE_DIR') ? SITE_DIR : '/';

		return (strpos($uri, $siteDir . 'catalog/') !== false
			|| strpos($uri, $siteDir . 'product/') !== false);
	}
}

if (!function_exists('vilmedDisablePullOnStorefront')) {
	/** Stop Bitrix Pull on public pages (pull.client, solid.ws). */
	function vilmedDisablePullOnStorefront(): void
	{
		if (!vilmedIsStorefrontRequest()) {
			return;
		}
		if (!defined('BX_PULL_SKIP_INIT')) {
			define('BX_PULL_SKIP_INIT', true);
		}
		if (!\Bitrix\Main\Loader::includeModule('pull')) {
			return;
		}

		$em = \Bitrix\Main\EventManager::getInstance();
		$em->removeEventHandler('main', 'OnProlog', ['CPullOptions', 'OnProlog']);
		$em->removeEventHandler('main', 'OnEpilog', ['CPullOptions', 'OnEpilog']);
	}
}

if (!function_exists('vilmedStripPullOnStorefront')) {
	/** Public pages: drop Bitrix Pull stack from HTML. */
	function vilmedStripPullOnStorefront(string &$content): void
	{
		if (!vilmedIsStorefrontRequest()) {
			return;
		}

		$stripNeedles = [
			'pull.client',
			'pull/protobuf',
			'rest.client',
			'dexie.bitrix',
		];

		$content = preg_replace_callback(
			'/<script(\s[^>]*?\ssrc="([^"]+)"[^>]*)>\s*<\/script>/i',
			static function (array $m) use ($stripNeedles): string {
				foreach ($stripNeedles as $needle) {
					if (stripos($m[2], $needle) !== false) {
						return '';
					}
				}

				return $m[0];
			},
			$content
		);

		$content = preg_replace(
			'/<script[^>]*>\s*BX\.bind\(window,\s*"load",\s*function\(\)\{BX\.PULL\.start\(\);\}\);\s*<\/script>/i',
			'',
			$content
		);

		$content = preg_replace(
			'#,\s*[\'"]/bitrix/js/pull/[^\'"]+[\'"]#i',
			'',
			$content
		);
		$content = preg_replace(
			'#,\s*[\'"]/bitrix/js/rest/[^\'"]+[\'"]#i',
			'',
			$content
		);
	}
}

if (!function_exists('vilmedDeferCatalogStylesheets')) {
	/**
	 * Catalog/product: defer non-blocking CSS.
	 *  - ui.font.opensans (web font)
	 *
	 * NB: the main compiled template_*_v1.css (data-template-style) must stay
	 * render-blocking — deferring it caused a ~1s flash of unstyled content (FOUC).
	 */
	function vilmedDeferCatalogStylesheets(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsCatalogLike'])) {
			return;
		}

		$patterns = [
			'ui\\.font\\.opensans',
		];

		vilmedDeferStylesheetLinks($content, $patterns);
	}
}

if (!function_exists('vilmedPlaceholderImg')) {
	function vilmedPlaceholderImg(): string
	{
		return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
	}
}

if (!function_exists('vilmedDeferImgSrcInFragment')) {
	function vilmedDeferImgSrcInFragment(string $html): string
	{
		$ph = vilmedPlaceholderImg();

		return preg_replace(
			'/\ssrc="(\/[^"]+\.(?:webp|jpe?g|png))"/i',
			' src="' . $ph . '" data-vilmed-src="$1" fetchpriority="low"',
			$html
		);
	}
}

if (!function_exists('vilmedDeferHomeOffscreenImages')) {
	/**
	 * Homepage first paint: skip network for hidden catalog tabs + sidebar vendor logos.
	 * Images load on tab click / IntersectionObserver (see main.js).
	 */
	function vilmedDeferHomeOffscreenImages(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsHome'])) {
			return;
		}

		$hasRecommend = stripos($content, 'tabs__box recommend') !== false;
		$deferTabClasses = ['tabs__box hit', 'tabs__box discount'];
		if ($hasRecommend) {
			$deferTabClasses[] = 'tabs__box new';
		}

		foreach ($deferTabClasses as $boxClass) {
			$quoted = preg_quote($boxClass, '/');
			$content = preg_replace_callback(
				'/(<div class="' . $quoted . '"[^>]*>)(.*?)(<\/div>\s*(?=<div class="tabs__box|<div class="clr"))/is',
				static function (array $m): string {
					return $m[1] . vilmedDeferImgSrcInFragment($m[2]) . $m[3];
				},
				$content
			);
		}

		if (preg_match('/(<div class="left-column"[^>]*>)(.*?)(<\/div>\s*<main class="workarea)/is', $content, $leftMatch)) {
			$inner = $leftMatch[2];
			$vendorCount = 0;
			$inner = preg_replace_callback(
				'/\ssrc="(\/[^"]+\.(?:webp|jpe?g|png))"/i',
				static function (array $m) use (&$vendorCount): string {
					$vendorCount++;
					if ($vendorCount <= 2) {
						return ' src="' . $m[1] . '"';
					}

					return ' src="' . vilmedPlaceholderImg() . '" data-vilmed-src="' . $m[1] . '" fetchpriority="low"';
				},
				$inner
			);
			$content = str_replace($leftMatch[0], $leftMatch[1] . $inner . $leftMatch[3], $content);
		}
	}
}

if (!function_exists('vilmedDeferCatalogOffscreenImages')) {
	/**
	 * Catalog listing: load first N cards immediately, defer the rest
	 * (placeholder + data-vilmed-src / data-vilmed-srcset, loaded via IntersectionObserver).
	 * Neutralizes both <source srcset> (webp) and <img src> inside each deferred <picture>.
	 */
	function vilmedDeferCatalogOffscreenImages(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsCatalogLike'])) {
			return;
		}

		$start = strpos($content, '<div class="catalog-item-list-view"');
		if ($start === false) {
			return; // not a listing page (e.g. product detail)
		}

		$skip = 8; // cards above-the-fold kept eager
		$count = 0;
		$ph = vilmedPlaceholderImg();

		$head = substr($content, 0, $start);
		$tail = substr($content, $start);

		$tail = preg_replace_callback(
			'#<picture>(.*?)</picture>#is',
			static function (array $m) use (&$count, $skip, $ph): string {
				$count++;
				if ($count <= $skip) {
					return $m[0];
				}
				$inner = $m[1];
				// W3C: <source> требует непустой srcset — оставляем placeholder, URL в data-*
				$inner = preg_replace(
					'/\ssrcset="([^"]+)"/i',
					' srcset="' . $ph . '" data-vilmed-srcset="$1"',
					$inner
				);
				$inner = preg_replace(
					'/\ssrc="(\/[^"]+\.(?:webp|jpe?g|png))"/i',
					' src="' . $ph . '" data-vilmed-src="$1" fetchpriority="low"',
					$inner
				);

				return '<picture>' . $inner . '</picture>';
			},
			$tail
		);

		$content = $head . $tail;
	}
}

if (!function_exists('vilmedInjectHomeDeferredLoader')) {
	function vilmedInjectHomeDeferredLoader(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsHome']) && empty($GLOBALS['vilmedIsCatalogLike'])) {
			return;
		}

		// Снять прошлые инъекции (двойной OnEndBufferContent / композит)
		$content = preg_replace(
			'/<script\b[^>]*\bid=["\']vilmed-deferred-images["\'][^>]*>.*?<\/script>/is',
			'',
			$content
		) ?? $content;

		$script = '<script id="vilmed-deferred-images">'
			. 'window.vilmedLoadDeferredImages=function(r){var s=r||document;'
			. 's.querySelectorAll("source[data-vilmed-srcset]").forEach(function(el){var u=el.getAttribute("data-vilmed-srcset");if(u){el.setAttribute("srcset",u);el.removeAttribute("data-vilmed-srcset");}});'
			. 's.querySelectorAll("img[data-vilmed-src]").forEach(function(i){'
			. 'var u=i.getAttribute("data-vilmed-src");if(u&&(!i.src||i.src.indexOf("data:image/gif")!==-1)){i.src=u;i.removeAttribute("data-vilmed-src");}});};'
			. 'document.addEventListener("DOMContentLoaded",function(){'
			. 'if("IntersectionObserver" in window){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){var sc=e.target.closest("picture")||e.target;vilmedLoadDeferredImages(sc);io.unobserve(e.target);}});},{rootMargin:"200px"});'
			. 'document.querySelectorAll("img[data-vilmed-src]").forEach(function(i){io.observe(i);});}'
			. 'else{vilmedLoadDeferredImages(document);}'
			. 'var vb=document.querySelector(".tabs-main .tabs__box[style*=block]")||document.querySelector(".tabs-main .tabs__box");'
			. 'if(vb){vilmedLoadDeferredImages(vb);}'
			. '});</script>';

		if (stripos($content, '</body>') !== false) {
			$content = preg_replace('/<\/body>/i', $script . '</body>', $content, 1) ?? $content;
		}
	}
}

if (!function_exists('vilmedNormalizeNoindexTags')) {
	/**
	 * Yandex <noindex> is not valid HTML5 — validators flag it on every page.
	 * Convert to HTML comments (Yandex still honors <!--noindex-->…<!--/noindex-->).
	 */
	function vilmedNormalizeNoindexTags(string &$content): void
	{
		if ($content === '' || stripos($content, 'noindex') === false) {
			return;
		}
		$content = preg_replace('/<\s*noindex\s*>/i', '<!--noindex-->', $content) ?? $content;
		$content = preg_replace('/<\s*\/\s*noindex\s*>/i', '<!--/noindex-->', $content) ?? $content;
	}
}

if (!function_exists('vilmedFixVmdMarkup')) {
	/** Runtime: SVG primitives in .ic + orphan FAQ summary (уже сохранённый DETAIL_TEXT). */
	function vilmedFixVmdMarkup(string &$content): void
	{
		if ($content === '') {
			return;
		}

		// ; между атрибутами (AI-контент)
		if (strpos($content, '";') !== false || strpos($content, "';") !== false) {
			$content = preg_replace('/(["\'])\s*;\s+(?=[a-zA-Z_][\w:-]*=)/', '$1 ', $content) ?? $content;
		}
		if (stripos($content, '</br') !== false) {
			$content = preg_replace('#</br\s*>#i', '<br>', $content) ?? $content;
		}

		if (stripos($content, 'class="ic"') === false && stripos($content, 'vmd-faq') === false && stripos($content, '<mark') === false) {
			return;
		}

		$content = preg_replace_callback(
			'#(<div\b[^>]*\bclass="[^"]*\bic\b[^"]*"[^>]*>)(.*?)(</div>)#is',
			static function (array $m): string {
				$inner = $m[2];
				if (stripos($inner, '<svg') !== false) {
					return $m[0];
				}
				if (!preg_match('#<(?:circle|rect|path|line|polyline|polygon|g)\b#i', $inner)) {
					return $m[0];
				}

				return $m[1]
					. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
					. $inner
					. '</svg>'
					. $m[3];
			},
			$content
		) ?? $content;

		$content = preg_replace_callback(
			'#(.{0,24})<summary>(.*?)</summary>\s*(<div\b[^>]*\bclass="[^"]*\bvmd-faq__a\b[^"]*"[^>]*>.*?</div>)#is',
			static function (array $m): string {
				if (preg_match('/<details\b[^>]*>\s*$/i', $m[1])) {
					return $m[0];
				}

				return $m[1] . '<details><summary>' . $m[2] . '</summary>' . $m[3] . '</details>';
			},
			$content
		) ?? $content;
		$content = preg_replace('#<details(\s[^>]*)?>\s*<details(\s[^>]*)?>#i', '<details>', $content) ?? $content;
		$content = preg_replace('#</details>\s*</details>#i', '</details>', $content) ?? $content;

		$content = preg_replace('#<mark\b([^>]*)>#i', '<span class="vmd-mark"$1>', $content) ?? $content;
		$content = preg_replace('#</mark>#i', '</span>', $content) ?? $content;
	}
}

if (!function_exists('vilmedEscapeScriptHtmlEndTags')) {
	/**
	 * Наивные HTML-чекеры считают </div></i></span> внутри JS как разметку страницы.
	 * В JS-строках экранируем как <\/…> (значение для браузера то же).
	 * type="text/html" (Bitrix-шаблоны) — см. vilmedEncodeTextHtmlTemplates.
	 */
	function vilmedEscapeScriptHtmlEndTags(string &$content): void
	{
		if ($content === '' || stripos($content, '<script') === false) {
			return;
		}

		$content = preg_replace_callback(
			'#<script(\b[^>]*)>(.*?)</script>#is',
			static function (array $m): string {
				$attrs = $m[1];
				$body = $m[2];
				if (preg_match('/\bsrc\s*=/i', $attrs)) {
					return $m[0];
				}
				if (preg_match('/\btype\s*=\s*["\']?\s*text\/(?:html|template)/i', $attrs)) {
					return $m[0];
				}
				$body = preg_replace('#</(div|i|span)>#i', '<\\/$1>', $body) ?? $body;

				return '<script' . $attrs . '>' . $body . '</script>';
			},
			$content
		) ?? $content;
	}
}

if (!function_exists('vilmedEncodeTextHtmlTemplates')) {
	/**
	 * Bitrix UI: <script type="text/html">…<div>…</div>…</script> (sale.location и др.).
	 * Наивные SEO-чекеры видят лишние </div></span></script> на каждой странице.
	 * Кодируем разметку в entities: в DOM скрипта браузер декодирует обратно в HTML
	 * (templates[k].innerHTML в core_ui_widget.js получает тот же текст).
	 */
	function vilmedEncodeTextHtmlTemplates(string &$content): void
	{
		if ($content === '' || stripos($content, 'text/html') === false) {
			return;
		}

		$content = preg_replace_callback(
			'#<script(\b[^>]*\btype\s*=\s*["\']text/(?:html|template)["\'][^>]*)>(.*?)</script>#is',
			static function (array $m): string {
				$body = $m[2];
				if ($body === '' || (strpos($body, '<') === false && strpos($body, '>') === false)) {
					return $m[0];
				}
				// Уже закодировано
				if (strpos($body, '&lt;') !== false && strpos($body, '<') === false) {
					return $m[0];
				}

				return '<script' . $m[1] . '>'
					. htmlspecialchars($body, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')
					. '</script>';
			},
			$content
		) ?? $content;
	}
}

if (!function_exists('vilmedStripInvalidCss')) {
	/**
	 * W3C CSS Parse Error на каждой странице:
	 * - Bitrix popup CSS с IE filter:alpha(...)
	 * - Bitrix minified @keyframes с «0{» вместо «0%{»
	 * - background-color:none (невалидно) → transparent
	 * Также убираем пустые <style> в body.
	 */
	function vilmedStripInvalidCss(string &$content): void
	{
		if ($content === '') {
			return;
		}
		if (stripos($content, 'filter:alpha') !== false) {
			$content = preg_replace('/;?\s*filter\s*:\s*alpha\([^)]*\)\s*;?/i', ';', $content) ?? $content;
		}
		// Bitrix core popup: @keyframes name{0{...}100%{...}} → 0%{
		if (stripos($content, 'keyframes') !== false) {
			$content = preg_replace('/(@[-a-z]*keyframes[^{]+\{)0\{/i', '${1}0%{', $content) ?? $content;
		}
		if (stripos($content, 'background-color') !== false) {
			$content = preg_replace('/background-color\s*:\s*none\b/i', 'background-color:transparent', $content) ?? $content;
		}
		// пустые / whitespace-only style в body
		$content = preg_replace('#<style\b[^>]*>\s*</style>#i', '', $content) ?? $content;
	}
}

if (!function_exists('vilmedHoistBodyStylesToHead')) {
	/** W3C: <style> нельзя в div/p — переносим из body в </head>. */
	function vilmedHoistBodyStylesToHead(string &$content): void
	{
		if ($content === '' || stripos($content, '<style') === false) {
			return;
		}
		if (!preg_match('/<body\b[^>]*>/i', $content, $bm, PREG_OFFSET_CAPTURE)) {
			return;
		}
		$bodyStart = (int)$bm[0][1] + strlen($bm[0][0]);
		$headClose = stripos($content, '</head>');
		if ($headClose === false || $headClose > $bodyStart) {
			return;
		}

		$body = substr($content, $bodyStart);
		$moved = [];
		$body = preg_replace_callback(
			'#<style\b[^>]*>.*?</style>#is',
			static function (array $m) use (&$moved): string {
				$moved[] = $m[0];

				return '';
			},
			$body
		) ?? $body;
		if ($moved === []) {
			return;
		}
		$content = substr($content, 0, $headClose)
			. implode('', $moved)
			. substr($content, $headClose, $bodyStart - $headClose)
			. $body;
	}
}

if (!function_exists('vilmedEncodeCatalogSearchHrefs')) {
	/** W3C: пробелы в /catalog/?q=… запрещены — кодируем q. */
	function vilmedEncodeCatalogSearchHrefs(string &$content): void
	{
		if ($content === '' || stripos($content, '/catalog/?q=') === false) {
			return;
		}
		$content = preg_replace_callback(
			'#\bhref="(/catalog/\?q=)([^"]*)"#u',
			static function (array $m): string {
				$prefix = $m[1];
				$rest = $m[2];
				if (!preg_match('/^([^&]*)(.*)$/u', $rest, $qm)) {
					return $m[0];
				}
				$q = (string)$qm[1];
				if ($q === '' || strpos($q, ' ') === false) {
					return $m[0];
				}
				$decoded = rawurldecode(str_replace('+', ' ', $q));

				return 'href="' . $prefix . rawurlencode($decoded) . $qm[2] . '"';
			},
			$content
		) ?? $content;
	}
}

if (!function_exists('vilmedDemoteExtraH1')) {
	/**
	 * SEO: после page H1 (#pagetitle или первый) все остальные H1 → H2
	 * (типично .vmd-desc категорий).
	 */
	function vilmedDemoteExtraH1(string &$content): void
	{
		if ($content === '' || stripos($content, '<h1') === false) {
			return;
		}
		if (preg_match('#<h1\b[^>]*\bid\s*=\s*["\']pagetitle["\'][^>]*>[\s\S]*?</h1>#i', $content, $m, PREG_OFFSET_CAPTURE)) {
			$end = (int)$m[0][1] + strlen($m[0][0]);
		} elseif (preg_match('#<h1\b[^>]*>[\s\S]*?</h1>#i', $content, $m, PREG_OFFSET_CAPTURE)) {
			$end = (int)$m[0][1] + strlen($m[0][0]);
		} else {
			return;
		}
		$head = substr($content, 0, $end);
		$tail = substr($content, $end);
		if ($tail === '' || stripos($tail, '<h1') === false) {
			return;
		}
		$tail = preg_replace('#<h1(\b[^>]*)>#i', '<h2$1>', $tail) ?? $tail;
		$tail = preg_replace('#</h1>#i', '</h2>', $tail) ?? $tail;
		$content = $head . $tail;
	}
}

if (!function_exists('vilmedDropH2MatchingPageH1')) {
	/**
	 * SEO: если первый H2 = текст page H1 — убрать (типично vendors «Оборудование компании X»).
	 */
	function vilmedDropH2MatchingPageH1(string &$content): void
	{
		if ($content === '' || !preg_match('#<h1\b[^>]*>([\s\S]*?)</h1>#i', $content, $m)) {
			return;
		}
		$h1 = trim(preg_replace('/\s+/u', ' ', strip_tags($m[1])) ?? '');
		if ($h1 === '') {
			return;
		}
		$h1Norm = function_exists('mb_strtolower') ? mb_strtolower($h1, 'UTF-8') : strtolower($h1);
		$done = false;
		$content = preg_replace_callback(
			'#<h2\b[^>]*>([\s\S]*?)</h2>#i',
			static function (array $mm) use ($h1Norm, &$done): string {
				if ($done) {
					return $mm[0];
				}
				$t = trim(preg_replace('/\s+/u', ' ', strip_tags($mm[1])) ?? '');
				$tNorm = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
				if ($tNorm === $h1Norm) {
					$done = true;

					return '';
				}

				return $mm[0];
			},
			$content
		) ?? $content;
	}
}

if (!function_exists('vilmedNormalizeProductHeadingHierarchy')) {
	/**
	 * После page H1: лишние H1→H2; H3/H4 до первого H2 → H2.
	 * Карточки и категории (.vmd-desc).
	 */
	function vilmedNormalizeProductHeadingHierarchy(string &$content): void
	{
		if ($content === '') {
			return;
		}
		if (!preg_match('#<h1\b[^>]*>[\s\S]*?</h1>#i', $content, $m, PREG_OFFSET_CAPTURE)) {
			return;
		}
		$end = (int)$m[0][1] + strlen($m[0][0]);
		$head = substr($content, 0, $end);
		$tail = substr($content, $end);
		if ($tail === '' || !preg_match('#<h[1-6]\b#i', $tail)) {
			return;
		}

		if (class_exists('\\Titlo\\Relevance\\CatalogRepository')
			&& method_exists('\\Titlo\\Relevance\\CatalogRepository', 'normalizeHeadingHierarchy')
		) {
			$tail = \Titlo\Relevance\CatalogRepository::normalizeHeadingHierarchy($tail);
		} else {
			$tail = preg_replace('#<h1(\b[^>]*)>#i', '<h2$1>', $tail) ?? $tail;
			$tail = preg_replace('#</h1>#i', '</h2>', $tail) ?? $tail;
			if (preg_match('#<h2\b#i', $tail)) {
				$parts = preg_split('#(?=<h2\b)#i', $tail, 2);
				if (is_array($parts) && isset($parts[0], $parts[1])) {
					$before = preg_replace('#<h([34])(\b[^>]*)>#i', '<h2$2>', $parts[0]) ?? $parts[0];
					$before = preg_replace('#</h[34]>#i', '</h2>', $before) ?? $before;
					$tail = $before . $parts[1];
				}
			} elseif (preg_match('#<h3\b#i', $tail)) {
				$tail = preg_replace('#<h3(\b[^>]*)>#i', '<h2$1>', $tail) ?? $tail;
				$tail = preg_replace('#</h3>#i', '</h2>', $tail) ?? $tail;
			} elseif (preg_match('#<h4\b#i', $tail)) {
				$tail = preg_replace('#<h4(\b[^>]*)>#i', '<h2$1>', $tail) ?? $tail;
				$tail = preg_replace('#</h4>#i', '</h2>', $tail) ?? $tail;
			}
		}

		$content = $head . $tail;
	}
}

if (!function_exists('vilmedFixContentMarkupBuffer')) {
	/**
	 * Runtime W3C fixes for already-saved DETAIL_TEXT (lists/headings/imgs).
	 * Persistent fix: CatalogRepository::sanitizeCatalogHtml + tools/perf/fix-w3c-*.php
	 */
	function vilmedFixContentMarkupBuffer(string &$content): void
	{
		if ($content === '') {
			return;
		}
		// `src="…"; title=` / `height:auto"title=`
		if (strpos($content, ';') !== false || preg_match('/["\'][a-zA-Z_:]/', $content)) {
			$content = preg_replace('/(["\'])\s*;\s+(?=[a-zA-Z_][\w:-]*=)/', '$1 ', $content) ?? $content;
			$content = preg_replace('/(["\'])(?=[a-zA-Z_:][\w:-]*=)/', '$1 ', $content) ?? $content;
		}
		// `p=""` на img/span
		if (stripos($content, ' p=') !== false || stripos($content, "\tp=") !== false) {
			$content = preg_replace('/\s+\bp\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content) ?? $content;
		}
		// `#ссс` (кириллица) → `#ccc`
		if (preg_match('/#[сcСC]{3}/u', $content)) {
			$content = preg_replace_callback('/#([сcСC]{3}|[сcСC]{6})\b/u', static function (array $m): string {
				$len = function_exists('mb_strlen') ? mb_strlen($m[1], 'UTF-8') : strlen($m[1]);
				return '#' . str_repeat('c', $len === 6 ? 6 : 3);
			}, $content) ?? $content;
		}
		// `<a name="x"></a>` → id на следующем заголовке / удалить
		if (stripos($content, 'name=') !== false) {
			$content = preg_replace_callback(
				'#<a\s+name\s*=\s*["\']?([^"\'>\s]+)["\']?\s*>\s*</a>\s*<(h[1-6])(\b[^>]*)>#i',
				static function (array $m): string {
					$tag = '<' . $m[2] . $m[3] . '>';
					if (preg_match('/\bid\s*=/i', $m[3])) {
						return $tag;
					}

					return '<' . $m[2] . $m[3] . ' id="' . htmlspecialchars($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';
				},
				$content
			) ?? $content;
			$content = preg_replace('#<a\s+name\s*=\s*["\']?([^"\'>\s]+)["\']?\s*>\s*</a>#i', '', $content) ?? $content;
		}
		// `<hN><div>…</div></hN>`
		if (preg_match('#<h[1-6]\b[^>]*>\s*<div\b#i', $content)) {
			$content = preg_replace(
				'#<(h[1-6])(\b[^>]*)>\s*<div\b[^>]*>([\s\S]*?)</div>\s*</\1>#i',
				'<$1$2>$3</$1>',
				$content
			) ?? $content;
		}
		// двойной `<li><li>…</li></li>` (контент; меню даёт `</ul></li>`, не `</li></li>`)
		if (preg_match('#<li\b[^>]*>\s*<li\b#i', $content) || preg_match('#</li>\s*</li>#i', $content)) {
			$content = preg_replace('#<li(\b[^>]*)>\s*<li\b[^>]*>#i', '<li$1>', $content) ?? $content;
			$content = preg_replace('#</li>\s*</li>#i', '</li>', $content) ?? $content;
		}
		// `<span><h2>…</h2></span>` / `<b><span><h2>`
		if (preg_match('#<span\b[^>]*>\s*<h[1-6]\b#i', $content)) {
			$content = preg_replace(
				'#<(?:b|strong)>\s*<span\b[^>]*>\s*(<h[1-6]\b[^>]*>[\s\S]*?</h[1-6]>)\s*</span>\s*</(?:b|strong)>#i',
				'$1',
				$content
			) ?? $content;
			$content = preg_replace(
				'#<span\b[^>]*>\s*(<h[1-6]\b[^>]*>[\s\S]*?</h[1-6]>)\s*</span>#i',
				'$1',
				$content
			) ?? $content;
		}
		// orphan <li> вне ul + починка ol>ul / h2 в ul / br в ol
		if ((stripos($content, '<li') !== false || stripos($content, '<ol') !== false || stripos($content, '<ul') !== false)
			&& class_exists('\\Titlo\\Relevance\\CatalogRepository')
		) {
			if (method_exists('\\Titlo\\Relevance\\CatalogRepository', 'fixListInnerBlocks')) {
				$content = \Titlo\Relevance\CatalogRepository::fixListInnerBlocks($content);
			}
			if (method_exists('\\Titlo\\Relevance\\CatalogRepository', 'fixNestedListMarkup')) {
				$content = \Titlo\Relevance\CatalogRepository::fixNestedListMarkup($content);
			}
			if (method_exists('\\Titlo\\Relevance\\CatalogRepository', 'wrapOrphanListItems')) {
				$content = \Titlo\Relevance\CatalogRepository::wrapOrphanListItems($content);
			}
		}
		// пустые tr / мусорные атрибуты / mso
		if (stripos($content, '<tr') !== false) {
			$content = preg_replace('#<tr\b[^>]*>\s*</tr>#i', '', $content) ?? $content;
		}
		if (preg_match('/\s\d+\s*=/', $content) || preg_match('/\d+px/u', $content)) {
			$content = preg_replace('/\s+\d+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/', '', $content) ?? $content;
			$content = preg_replace(
				'/\s+[^\s=<>]*\d+px;?[\"”\'\"]*\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/u',
				'',
				$content
			) ?? $content;
			$content = preg_replace(
				'/\s+(?:margin|padding)[^\s=<>]*\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
				'',
				$content
			) ?? $content;
		}
		if (stripos($content, 'mso-') !== false) {
			$content = preg_replace('/\s*mso-[a-z0-9-]+:[^;"]*;?/i', '', $content) ?? $content;
		}
		if (stripos($content, 'align=') !== false) {
			$content = preg_replace('/<(figure|div|p|table)(\s[^>]*?)\s+align\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)([^>]*)>/i', '<$1$2$3>', $content) ?? $content;
		}
		// heading-in-heading
		if (preg_match('#<h[1-6]\b[^>]*>[^<]*<h[1-6]\b#i', $content)) {
			$content = preg_replace(
				'#<(h[1-6])(\b[^>]*)>([^<]*?)\s*<(h[1-6])\b#i',
				'<$1$2>$3</$1><$4',
				$content
			) ?? $content;
		}
		// голый «< » только в sanitizeCatalogHtml / CLI — на полном HTML ломает JS (`a < b`)
		// безопасный вариант: «< » перед цифрой в тексте свойств (`мин. < 3`)
		if (preg_match('/<\s+\d/', $content)) {
			$content = preg_replace('/<(?=\s+\d)/', '&lt;', $content) ?? $content;
		}
		// <img> без alt
		if (stripos($content, '<img') !== false) {
			$content = preg_replace('/<img(?![^>]*\balt\s*=)(\s[^>]*)>/i', '<img alt=""$1>', $content) ?? $content;
		}
		// <h2>…<ul>…</h2> → <h2>…</h2><ul>…
		if (preg_match('#<h[1-6]\b[^>]*>[^<]*<(?:ul|ol)\b#i', $content)) {
			$content = preg_replace_callback(
				'#<(h[1-6])(\b[^>]*)>([\s\S]*?)</\1>#i',
				static function (array $m): string {
					$inner = $m[3];
					if (!preg_match('#<(?:ul|ol)\b#i', $inner)) {
						return $m[0];
					}
					$parts = preg_split('#(<(?:ul|ol)\b[^>]*>.*?</(?:ul|ol)>)#is', $inner, -1, PREG_SPLIT_DELIM_CAPTURE);
					$title = '';
					$lists = '';
					foreach ($parts as $part) {
						if ($part === '' || $part === null) {
							continue;
						}
						if (preg_match('#^<(?:ul|ol)\b#i', $part)) {
							$lists .= $part;
						} else {
							$title .= $part;
						}
					}
					$title = trim($title);
					$out = $title !== '' ? '<' . $m[1] . $m[2] . '>' . $title . '</' . $m[1] . '>' : '';

					return $out . $lists;
				},
				$content
			) ?? $content;
		}
		// <ul>/<ol> с прямыми <p> или голым текстом — оборачиваем в <li>
		foreach (['ul', 'ol'] as $list) {
			if (stripos($content, '<' . $list) === false) {
				continue;
			}
			$content = preg_replace_callback(
				'#<' . $list . '(\b[^>]*)>([\s\S]*?)</' . $list . '>#i',
				static function (array $m) use ($list): string {
					$inner = $m[2];
					$changed = false;
					// <li><p>…</p></li> → <li>…</li> (иначе p→li даёт <li><li>)
					$inner2 = preg_replace(
						'#(<li\b[^>]*>)\s*<p\b[^>]*>([\s\S]*?)</p>\s*(</li>)#i',
						'$1$2$3',
						$inner
					);
					if ($inner2 !== null && $inner2 !== $inner) {
						$inner = $inner2;
						$changed = true;
					}
					// оставшиеся <p>…</p> внутри списка → <li>…
					if (preg_match('#<p\b#i', $inner)) {
						$inner = preg_replace('#<p\b[^>]*>([\s\S]*?)</p>#i', '<li>$1</li>', $inner) ?? $inner;
						$changed = true;
					}
					// текст сразу после <ul> или </li> без <li>
					$inner2 = preg_replace_callback(
						'#(^|</li>)\s*([^<\s][^<]*?)(?=<li>|</' . $list . '>|<(?:ul|ol)\b)#iu',
						static function (array $mm): string {
							$text = trim($mm[2]);
							if ($text === '') {
								return $mm[0];
							}

							return $mm[1] . '<li>' . $text . '</li>';
						},
						$inner
					);
					if ($inner2 !== null && $inner2 !== $inner) {
						$inner = $inner2;
						$changed = true;
					}

					return $changed ? '<' . $list . $m[1] . '>' . $inner . '</' . $list . '>' : $m[0];
				},
				$content
			) ?? $content;
		}
		// orphan <li> прямо в <div> — только в sanitizeCatalogHtml / CLI (полный DOM опасен)
	}
}

if (!function_exists('vilmedOnEndBufferContent')) {
	function vilmedOnEndBufferContent(string &$content): void
	{
		// Никогда не трогаем админку / служебные URL Bitrix — иначе белый экран / битый JS
		if ((defined('ADMIN_SECTION') && ADMIN_SECTION) || defined('BX_CRONTAB')) {
			return;
		}
		$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
		if ($uri !== '' && (
			strncmp($uri, '/bitrix/admin/', 14) === 0
			|| strncmp($uri, '/bitrix/tools/', 14) === 0
			|| strncmp($uri, '/bitrix/services/', 17) === 0
			|| strncmp($uri, '/bitrix/components/', 19) === 0
		)) {
			return;
		}
		if ($content === '' || stripos($content, '<html') === false) {
			return;
		}

		vilmedInjectCriticalHomeCss($content);
		vilmedInjectLazyImages($content);
		vilmedInjectWebpImages($content);
		vilmedInjectLcpPreload($content);
		vilmedInjectBackgroundWebp($content);
		vilmedInjectFancyboxWebpHrefs($content);
		vilmedFixFontDisplay($content);
		vilmedDeferHomeStylesheets($content);
		vilmedDeferCatalogStylesheets($content);
		vilmedDeferHomeOffscreenImages($content);
		vilmedDeferCatalogOffscreenImages($content);
		vilmedDeferHomeScripts($content);
		vilmedDeferCatalogScripts($content);
		vilmedStripPullOnStorefront($content);
		vilmedResequenceCoreScripts($content);
		vilmedInjectHomeDeferredLoader($content);
		vilmedFixVmdMarkup($content);
		vilmedFixContentMarkupBuffer($content);
		vilmedDemoteExtraH1($content);
		vilmedDropH2MatchingPageH1($content);
		vilmedNormalizeProductHeadingHierarchy($content);
		vilmedEncodeCatalogSearchHrefs($content);
		vilmedEncodeTextHtmlTemplates($content);
		vilmedEscapeScriptHtmlEndTags($content);
		vilmedStripInvalidCss($content);
		vilmedHoistBodyStylesToHead($content);
		vilmedNormalizeNoindexTags($content);
	}
}

if (function_exists('AddEventHandler')) {
	AddEventHandler('main', 'OnEndBufferContent', 'vilmedOnEndBufferContent');
	AddEventHandler('main', 'OnPageStart', 'vilmedDisablePullOnStorefront');
	AddEventHandler('main', 'OnAfterFileSave', 'vilmedWebpOnAfterFileSave');
}

if (!function_exists('vilmedEnsureCssinliner')) {
	/** One-time prod fix: stop inlining 300+ KB CSS into HTML. */
	function vilmedEnsureCssinliner(): void
	{
		if (defined('ADMIN_SECTION') || $_SERVER['REQUEST_METHOD'] === 'POST') {
			return;
		}
		if (\COption::GetOptionString('main', 'vilmed_cssinliner_v2', '') === 'Y') {
			return;
		}
		if (!\CModule::IncludeModule('arturgolubev.cssinliner')) {
			return;
		}

		$moduleId = 'arturgolubev.cssinliner';
		$exceptions = implode("\n", [
			'colors.css',
			'template_styles.css',
			'template_styles.catalog',
			'template_styles.personal',
			'template_styles.compare',
			'font-awesome',
			'slider.css',
			'fancybox',
			'slick.css',
			'custom-forms.css',
			'ui.font',
			'opensans',
		]);

		\COption::SetOptionString($moduleId, 'inline_max_weight', '48');
		\COption::SetOptionString($moduleId, 'exceptions', $exceptions);
		\COption::SetOptionString('main', 'vilmed_cssinliner_v2', 'Y');
	}
}

if (function_exists('AddEventHandler')) {
	AddEventHandler('main', 'OnPageStart', 'vilmedEnsureCssinliner');
}

if (!function_exists('vilmedDeferStylesheet')) {
	/** Non-blocking CSS — for below-the-fold blocks (e.g. catalog cards on homepage). */
	function vilmedDeferStylesheet(string $href): void
	{
		$href = htmlspecialcharsbx($href, ENT_QUOTES);
		\Bitrix\Main\Page\Asset::getInstance()->addString(
			'<link rel="preload" as="style" href="' . $href . '" onload="this.onload=null;this.rel=\'stylesheet\'">'
			. '<noscript><link rel="stylesheet" href="' . $href . '"></noscript>',
			true
		);
	}
}
