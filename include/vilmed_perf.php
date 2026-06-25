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
				$replacement = '<picture><source srcset="' . htmlspecialcharsbx($webp, ENT_QUOTES) . '" type="image/webp">'
					. $fullMatch . '</picture>';
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

		if (vilmedIsMobileClient()) {
			// mobile-only extras handled in vilmedDeferHomeScripts
		}

		$content = preg_replace_callback(
			'/<link(\s[^>]+)>/i',
			static function (array $m) use ($patterns): string {
				if (!preg_match('#\brel=["\']stylesheet["\']#i', $m[1])) {
					return $m[0];
				}
				if (stripos($m[1], 'onload=') !== false) {
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
	 *  - data-template-style (main compiled template_*_v1.css) — experimental,
	 *    may cause brief FOUC; loads via media=print/onload swap.
	 */
	function vilmedDeferCatalogStylesheets(string &$content): void
	{
		if (empty($GLOBALS['vilmedIsCatalogLike'])) {
			return;
		}

		$patterns = [
			'ui\\.font\\.opensans',
			'data-template-style',
		];

		$content = preg_replace_callback(
			'/<link(\s[^>]+)>/i',
			static function (array $m) use ($patterns): string {
				if (!preg_match('#\brel=["\']stylesheet["\']#i', $m[1])) {
					return $m[0];
				}
				if (stripos($m[1], 'onload=') !== false) {
					return $m[0];
				}
				foreach ($patterns as $pattern) {
					if (preg_match('#' . $pattern . '#i', $m[1])) {
						return '<link' . $m[1] . ' media="print" onload="this.media=\'all\'"><noscript>' . $m[0] . '</noscript>';
					}
				}

				return $m[0];
			},
			$content
		);
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
				$inner = preg_replace(
					'/\ssrcset="([^"]+)"/i',
					' data-vilmed-srcset="$1"',
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
		if (stripos($content, 'vilmed-deferred-images') !== false) {
			return;
		}

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
			$content = str_replace('</body>', $script . '</body>', $content);
		}
	}
}

if (!function_exists('vilmedOnEndBufferContent')) {
	function vilmedOnEndBufferContent(string &$content): void
	{
		vilmedInjectCriticalHomeCss($content);
		vilmedInjectLcpPreload($content);
		vilmedInjectLazyImages($content);
		vilmedInjectWebpImages($content);
		vilmedInjectBackgroundWebp($content);
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
