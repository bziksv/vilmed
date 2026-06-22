<?php
namespace IX;

class ApiadminBillzAi {	
	public static function GetAuthParams()
	{
		return array(
			'secret_token',
		);
	}
	
	public static function GetDownloadPath(&$path, &$arParams, &$arHeaders, &$arCookies)
	{
		$arHeaders['Accept'] = 'application/json';
		$arHeaders['Content-Type'] = 'application/json';
		$authParams = $arParams['VARS'];
		if($authParams['secret_token'] && function_exists('json_encode'))
		{
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>20, 'streamTimeout'=>20));
			foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
			$res = $ob->post('https://api-admin.billz.ai/v1/auth/login', json_encode(array('secret_token'=>$authParams['secret_token'])));
			$arRes = json_decode($res, true);
			if($arRes['data'] && $arRes['data']['access_token'])
			{
				$arHeaders['Authorization'] = 'Basic '.$arRes['data']['access_token'];
			}
		}

		return true;
	}
}
?>