<?php
$vilmedLogoSrc = '/logo/Vilmed-logo-min.png';
$vilmedLogoWebp = function_exists('vilmedEnsureWebpSrc') ? vilmedEnsureWebpSrc($vilmedLogoSrc) : null;
?>
<a href="<?=SITE_DIR?>"><?php
if ($vilmedLogoWebp !== null && function_exists('vilmedPictureHtml')) {
	echo vilmedPictureHtml(
		['SRC' => $vilmedLogoSrc, 'SRC_WEBP' => $vilmedLogoWebp, 'WIDTH' => 300, 'HEIGHT' => 86],
		['class' => 'no-lazy', 'alt' => 'Vilmed', 'title' => 'Vilmed']
	);
} else {
	?><img class="no-lazy" width="300" height="86" alt="Vilmed" src="<?=htmlspecialcharsbx($vilmedLogoSrc)?>" title="Vilmed"><?php
}
?></a>
