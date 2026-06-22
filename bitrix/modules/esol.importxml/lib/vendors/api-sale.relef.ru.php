<?php
namespace IX;

//https://api-sale.relef.ru/api/v1/products/list?limit=1000&offset={API_OFFSET_1000}
//Headers: Apikey

class Apisalerelefru {	
	public static function GetDownloadFile($arParams, $maxTime=20)
	{
		if(strpos($arParams['FILELINK'], '/api/v1/products/list')!==false && function_exists('json_encode'))
		{
			if($maxTime <= 1) $maxTime = 20;
			
			$arHeaders = $arCookies = array();
			$path = $arParams['FILELINK'];
			
			$path = \Bitrix\EsolImportxml\Utils::PathReplaceApiPages($path);
			$arUrl = parse_url($path);
			$arGet = array();
			parse_str($arUrl['query'], $arGet);
			$arPost = array(
				'limit' => ((int)$arGet['limit'] > 0 ? (int)$arGet['limit'] : 1000),
				'offset' => ((int)$arGet['offset'] > 0 ? (int)$arGet['offset'] : 0),
			);
			$path = explode('?', $path)[0];
			
		
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>10, 'streamTimeout'=>$maxTime));
			if(isset($arParams['HEADERS']) && is_array($arParams['HEADERS']))
			{
				foreach($arParams['HEADERS'] as $k=>$v)
				{
					$ob->setHeader($k, $v);
				}
			}
			$fContent = $ob->post($path, json_encode($arPost));
			
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
}
?>