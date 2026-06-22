<?php
namespace IX;

class Richledru {
	public static function GetAuthParams()
	{
		return array(
			'login',
			'password'
		);
	}
	
	public static function GetDownloadPath(&$path, &$arParams, &$arHeaders, &$arCookies)
	{
		$token = '';
		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'socketTimeout'=>20, 'streamTimeout'=>20));
		foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
		$res = $ob->get('https://richled.ru/account/profile');
		$res = html_entity_decode($res);
		if(preg_match('/"csrfToken"\s*:\s*"([^"]*)"/', $res, $m)) $token = $m[1];

		$arCookies = array_merge($arCookies, $ob->getCookies()->toArray());

		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'socketTimeout'=>20, 'streamTimeout'=>20, 'redirect'=>false));
		foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
		$ob->setCookies($arCookies);
		$res = $ob->post('https://richled.ru/login.json', array('csrf_token'=>$token, 'login'=>$arParams['VARS']['login'], 'password'=>$arParams['VARS']['password']));
		$arCookies = array_merge($arCookies, $ob->getCookies()->toArray());
		
		return true;
	}
}
?>