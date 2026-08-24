<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/legal/render.php';
vilmedRenderLegalPage('Согласие на обработку персональных данных', 'consent_content.php');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
