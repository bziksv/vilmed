<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/legal/render.php';
vilmedRenderLegalPage('Политика обработки персональных данных', 'personal_data_content.php');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
