<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/legal/render.php';
vilmedRenderLegalPage('Политика использования cookie-файлов', 'cookie_content.php');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
