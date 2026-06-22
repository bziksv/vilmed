<?php
namespace IX;

class B2bipmatikaru {	
	public static function GetAuthParams()
	{
		return array(
			'login',
			'password',
		);
	}
	
	public static function GetDownloadPath(&$path, &$arParams, &$arHeaders, &$arCookies)
	{
		$authParams = $arParams['VARS'];
		if($authParams['login'] && $authParams['password'] && function_exists('json_decode'))
		{
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'socketTimeout'=>20, 'streamTimeout'=>20));
			$res = $ob->post('https://b2b-ipmatika.ru/api/v1/auth', array('login'=>$authParams['login'], 'password'=>$authParams['password']));
			if($ob->getStatus()==200)
			{
				$arRes = json_decode($res, true);
				if(is_array($arRes) && $arRes['status']=='success' && isset($arRes['message']['Authorization']))
				{
					$arHeaders['Authorization'] = $arRes['message']['Authorization'];
				}
			}
		}

		return true;
	}
}
?>