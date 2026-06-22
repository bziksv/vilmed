<?php
namespace IX;

class Iproetmru {
	public static function GetAuthParams()
	{
		return array(
			'login',
			'password'
		);
	}
	
	public static function GetDownloadPath(&$path, &$arParams, &$arHeaders, &$arCookies)
	{
		if(!function_exists('json_decode')) return true;
		if(ToLower(explode('?', $path)[0])!=='https://ipro.etm.ru/api/v1/goods/') return true;
		
		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'socketTimeout'=>20, 'streamTimeout'=>20));
		$res = $ob->post('https://ipro.etm.ru/api/v1/user/login?log='.$arParams['VARS']['login'].'&pwd='.$arParams['VARS']['password'], array());
		$arRes = json_decode($res, true);
		$sessionID = (isset($arRes['data']['session']) ? $arRes['data']['session'] : '');
		
		if(!$sessionID) return true;
		
		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'socketTimeout'=>20, 'streamTimeout'=>20));
		$res = $ob->get('https://ipro.etm.ru/api/v1/job/create/40029846?session-id='.$sessionID);
		$arRes = json_decode($res, true);
		$uuid = (isset($arRes['data']['uuid']) ? $arRes['data']['uuid'] : '');
		
		if(!$uuid) return true;
		
		$state = 0;
		$timeStart = microtime(true);
		
		while($state==0 && microtime(true)-$timeStart < 180)
		{
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'socketTimeout'=>20, 'streamTimeout'=>20));
			$res = $ob->get('https://ipro.etm.ru/api/v1/job/'.$uuid.'?session-id='.$sessionID);
			$arRes = json_decode($res, true);
			$state = (isset($arRes['data']['rows'][0]['state']) ? $arRes['data']['rows'][0]['state'] : 0);
			if($state==0) sleep(3);
			elseif($state==1) $path = $arRes['data']['rows'][0]['urls'][0]['url'];
		}
		
		return true;
	}
}
?>