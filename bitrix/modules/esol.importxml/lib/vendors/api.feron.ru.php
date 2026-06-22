<?php
//Headers:
//Authorization: server token

namespace IX;

class Apiferonru {	
	
	public static function GetDownloadFile($arParams, $maxTime=20)
	{
		if(!function_exists('json_decode') || !function_exists('curl_init')) return false;
		if($maxTime <= 1) $maxTime = 20;
		
		if(preg_match('/^https?:\/\/api\.feron\.ru\/offers\/products\/search/is', $arParams['FILELINK'], $m))
		{
			session_start();
			$sessionVarName = 'IMPORT_XML_FERON_SEARCH_TOKEN';
			$arHeaders = array(
				'Content-type: application/json'
			);
			if(is_array($arParams['HEADERS']))
			{
				foreach($arParams['HEADERS'] as $k=>$v)
				{
					$arHeaders[] = $k.': '.$v;
				}
			}
			
			$arPost = array('size'=>1000);
			if(preg_match('/#page=(\d+)/', $arParams['FILELINK'], $m) && (int)$m[1] > 1 && $_SESSION[$sessionVarName])
			{
				$arPost = array('search_token' => $_SESSION[$sessionVarName]);
			}
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($arPost));
			curl_setopt($ch, CURLOPT_URL, $arParams['FILELINK']);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $arHeaders);
			curl_setopt($ch, CURLOPT_TIMEOUT, $maxTime);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$fContent = curl_exec($ch);
			curl_close($ch);

			$arRes = json_decode($fContent, true);
			if($arRes['search_token']) $_SESSION[$sessionVarName] = $arRes['search_token'];
			
			$sess = $_SESSION;
			session_write_close();
			$_SESSION = $sess;
			
			$tmpPath = \CFile::GetTempName('', 'products.json');
			$dir = \Bitrix\Main\IO\Path::getDirectory($tmpPath);
			\Bitrix\Main\IO\Directory::createDirectory($dir);
			file_put_contents($tmpPath, $fContent);
			$arFile = \CFile::MakeFileArray($tmpPath);
			\Bitrix\EsolImportxml\Utils::CheckJsonFile($arFile);
			
			return $arFile;
		}
		
		return false;
	}
	
	public static function IsApiService()
	{
		return true;
	}
	
	public static function GetApiServicePath(&$path, $page)
	{
		return preg_replace('/#page=\d+/', '', $path).'#page='.$page;
	}
}
?>