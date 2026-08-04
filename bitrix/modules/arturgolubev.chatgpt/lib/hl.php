<?
namespace Arturgolubev\Chatgpt; //1.1.2

use \Bitrix\Main\Loader;
use \Bitrix\Highloadblock; 

class Hl {
	static function getPropValueField($arProperty, $value, $field = 'UF_NAME'){
		// echo '<pre>'; print_r($arProperty); echo '</pre>';
		// echo '<pre>'; print_r($value); echo '</pre>';
		
		if(is_array($value)){
			$result = array();
			
			foreach($value as $k=>$v){
				if(!$v){
					unset($value[$k]);
				}
			}
			
			if(!count($value)) return $result;
		}else{
			$result = '';
		}
		
		if($value && $arProperty["USER_TYPE_SETTINGS"]["TABLE_NAME"] && Loader::includeModule('highloadblock')){
			$hlblockDB = Highloadblock\HighloadBlockTable::getList(
				array("filter" => array(
					'TABLE_NAME' => $arProperty["USER_TYPE_SETTINGS"]["TABLE_NAME"]
				))
			);
			if($hlblock = $hlblockDB->fetch()){	
				$entity = Highloadblock\HighloadBlockTable::compileEntity($hlblock);
				$entity_data_class = $entity->getDataClass();
				
				$res = $entity_data_class::getList(array('filter'=>array('UF_XML_ID' => $value)));
				while($item = $res->fetch()){
					if(is_array($result)){
						$result[] = $item[$field];
					}else{
						$result = $item[$field];
					}
				}
			}
		}
		
		return $result;
	}
	
	static function getDataClassByID($hlID){
		Loader::includeModule('highloadblock');

		$hlblock = Highloadblock\HighloadBlockTable::getById($hlID)->fetch();
		$entity = Highloadblock\HighloadBlockTable::compileEntity($hlblock); 
		$entity_data_class = $entity->getDataClass();
		
		return $entity_data_class;
	}
}