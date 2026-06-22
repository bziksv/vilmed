<?php
namespace IX;

class Technosuccessru {	
	public static function GetAuthParams()
	{
		return array(
			'email',
			'password',
		);
	}
	
	public static function GetDownloadPath(&$path, &$arParams, &$arHeaders, &$arCookies)
	{
		$arHeaders['Accept'] = 'application/json';
		$arHeaders['Content-Type'] = 'application/json; charset=utf-8';
		$authParams = $arParams['VARS'];
		if($authParams['email'] && $authParams['password'] && function_exists('json_encode'))
		{
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>20, 'streamTimeout'=>20));
			foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
			$res = $ob->post('https://technosuccess.ru/api/authTokens', json_encode(array('email'=>$authParams['email'], 'password'=>$authParams['password'])));
			$arRes = json_decode($res, true);
			if($arRes['token'])
			{
				$arHeaders['Authorization'] = 'Basic '.base64_encode($arRes['token'].":");
			}
		}

		return true;
	}
}
?>