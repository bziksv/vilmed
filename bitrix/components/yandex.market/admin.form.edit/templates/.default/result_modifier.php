<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

/** @var \CBitrixComponentTemplate $this */

if (!isset($component))
{
	$component = $this->__component;
}

include __DIR__ . '/modifier/tab-request.php';
include __DIR__ . '/modifier/format-data.php';
include __DIR__ . '/modifier/special-fields.php';
include __DIR__ . '/modifier/required-highlight.php';
include __DIR__ . '/modifier/merge-empty-tab.php';
