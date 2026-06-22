<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

/** @noinspection SpellCheckingInspection */
return [
	'js' => [
		'searchadapter.js',
		'tag.js',
		'tagcollection.js',
		'node.js',
		'nodecollection.js',
	],
	'rel' => [
		'@lib.select2',
		'@Ui.Input.TagInput',
		'@Ui.Input.Template',
		'@Ui.Input.Formula',
		'@Source.Manager',
	],
];