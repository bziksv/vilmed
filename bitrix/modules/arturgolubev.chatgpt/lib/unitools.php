<?
namespace Arturgolubev\Chatgpt; //3.5.1

use \Bitrix\Main\Page\Asset,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Diag;

class Unitools {
	const MODULE_ID = 'arturgolubev.chatgpt';
	
	// storage
	public static $storage = [];
	public static function setStorage($type, $name, $value){
		if(!isset(self::$storage[$type])){
			self::$storage[$type] = [];
		}
		
		self::$storage[$type][$name] = $value;
	}
	public static function getStorage($type, $name){
		if(!isset(self::$storage[$type])){
			self::$storage[$type] = [];
		}
		
		return (isset(self::$storage[$type][$name]) ? self::$storage[$type][$name] : null);
	}
	
	// cache
	public static function fastPhpCache($cacheId, $cachePath, $time, $callback, $callbackArgs){
		$obCache = new \CPHPCache();
		if($obCache->InitCache($time, md5($cacheId), $cachePath)){
			$vars = $obCache->GetVars();
			$result = $vars['result'];
		}elseif($obCache->StartDataCache()){
			$result = call_user_func_array($callback, $callbackArgs);
			$obCache->EndDataCache(array('result' => $result));
		}

		return $result;
	}

	// logger
    public static function simpleDataLog($path, $module, $data){
        $realPath = $_SERVER['DOCUMENT_ROOT'].$path;

        $logger = Diag\Logger::create('main.Default', [$realPath, $showArgs]);
		if ($logger === null){
			$logger = new Diag\FileLogger($realPath, 0);
			$formatter = new Diag\LogFormatter($showArgs);
			$logger->setFormatter($formatter);
		}

		$context = [
			'module' => $module,
			'message' => $data,
		];

		$message = "Time: ".date('d.m.Y H:i:s')." (".date('D, d M Y H:i:s O').")\n"
			. ($module != '' ? "Title: {module}\n" : '')
			. "Data: {message}\n"
			. "{delimiter}\n\n"
		;

		$logger->debug($message, $context);
    }

	// settings
	static function setSetting($name, $value){
		Option::set(self::MODULE_ID, $name, $value);
	}
	static function getSetting($name, $def = false){
		return trim(Option::get(self::MODULE_ID, $name, $def));
	}
	
	static function getSiteSetting($name, $def = false){
		return trim(Option::get(self::MODULE_ID, $name.'_'.SITE_ID, $def));
	}

	static function getSiteSettingEx($name, $def = false){
		return trim(Option::get(self::MODULE_ID, $name.'_'.SITE_ID, Option::get(self::MODULE_ID, $name, $def)));
	}

	static function getBoolSetting($name, $def = false){
		return (self::getSetting($name, $def) == 'Y');
	}

	static function getBoolSiteSetting($name, $def = false){
		return (self::getSiteSetting($name, $def) == 'Y');
	}

	static function getIntSetting($name, $def = false, $emptyval = 0){
		$val = IntVal(self::getSetting($name, $def));
		if($val < 1 && $emptyval){
			$val = $emptyval;
		}
		return $val;
	}

	static function getIntSiteSetting($name, $def = false, $emptyval = 0){
		$val = IntVal(self::getSiteSetting($name, $def));
		if($val < 1 && $emptyval){
			$val = $emptyval;
		}
		return $val;
	}

	// globals
	static function isAdmin(){
		global $USER;
		if(!is_object($USER)) $USER = new \CUser();
		return $USER->IsAdmin();
	}
	
	static function addJs($script){
		Asset::getInstance()->addJs($script);
	}
	static function addCss($script, $p2 = true, $oldApi = false){
		if($oldApi){
			global $APPLICATION;
			$APPLICATION->SetAdditionalCSS($script, $p2);
		}else{
			Asset::getInstance()->addCss($script, $p2);
		}
	}
	static function addString($str, $unique = false, $location = \Bitrix\Main\Page\AssetLocation::AFTER_JS_KERNEL){
		Asset::getInstance()->addString($str, $unique, $location);
	}

	static public function getCurPage($index = false){
		$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
		
		if(!$index){
			return str_replace('/index.php', '/', $request->getRequestedPage());
		}else{
			return $request->getRequestedPage();
		}
	}
	static public function getCurPageParam(){
		$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
		return $request->getRequestUri();
	}
	
	// simple
	static function textOneLine($text){
		return str_replace(["\r\n", "\r", "\n"], '',  $text);
	}
	
	static function textSafeMode($text, $htsc = false){
		if($htsc) $text = htmlspecialchars_decode($text);
		
		$text = str_replace(["'", '"', '&', "\r\n", "\r", "\n"], "", $text);
		$text = preg_replace("/[\x1-\x8\xB-\xC\xE-\x1F]/", "", $text);
		
		if($htsc) $text = htmlspecialcharsbx($text);
		
		return $text;
	}
	
	// sort
	static function sort_by_sort_asc($a, $b){
		if ($a == $b){return 0;}
		return ($a["SORT"] < $b["SORT"]) ? -1 : 1;
	}
	static function sort_by_sort_desc($a, $b){
		if ($a == $b){return 0;}
		return ($a["SORT"] > $b["SORT"]) ? -1 : 1;
	}
	
	// regular
	static function onComposite(){
		return file_exists($_SERVER["DOCUMENT_ROOT"].BX_PERSONAL_ROOT."/html_pages/.enabled");
	}
	static function isJsonPage($page){
		return (substr(trim($page), 0, 1) == '{');
	}
	static function isHtmlPage($page){
		if(!defined("AG_CHECK_DOCTYPE")){
			if(defined("BX_UTF")){
				$t = (mb_stripos(mb_substr(trim($page),0,128), '<!DOCTYPE') === false) ? 0 : 1;
			}else{
				$t = (stripos(substr(trim($page),0,128), '<!DOCTYPE') === false) ? 0 : 1;
			}
			define('AG_CHECK_DOCTYPE', $t);
		}
		return AG_CHECK_DOCTYPE;
	}
	static function isAdminPage(){
		if(!isset(self::$storage["main"]["is_admin_page"])){
			$r = 0;
			
			if(defined("ADMIN_SECTION") && ADMIN_SECTION == true) $r = 1;
			if(defined("BX_CRONTAB") && BX_CRONTAB == true) $r = 1;
			
			if(strpos($_SERVER['PHP_SELF'], BX_ROOT.'/admin') === 0) $r = 1;
			if(strpos($_SERVER['PHP_SELF'], BX_ROOT.'/tools') === 0) $r = 1;
			
			self::setStorage("main", "is_admin_page", $r);
		}else{
			$r = self::getStorage("main", "is_admin_page");
		}
		
		return $r;
	}
	static function checkStatus(){
		if(!isset(self::$storage["main"]["status"]))
		{
			$r = (self::isAdminPage() || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST')) ? 0 : 1;
			self::setStorage("main", "status", $r);
		}
		else
			$r = self::getStorage("main", "status");
		
		return $r;
	}
	
	static function checkAjax(){
		$check = (strtolower($_REQUEST['ajax']) == 'y' || (isset($_REQUEST["bxajaxid"]) && strlen($_REQUEST["bxajaxid"]) > 0)) ? 0 : 1;
		if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') $check = 0;
		return $check;
	}
	
	static function checkPageException($pages){
		if($pages){
			$cur = self::getCurPage();
			$curParams = self::getCurPageParam();

			$serverName = \Bitrix\Main\Application::getInstance()->getContext()->getServer()->getServerName();
			
			if(!is_array($pages)){
				$pages = self::explodeByEOL($pages);
			}

			foreach($pages as $page){
				$quoted = preg_quote($page, '/');
				$pattern = '/^'.str_replace('\\*', '.*', $quoted).'$/sU';
				if(preg_match($pattern, $cur) || preg_match($pattern, $curParams) || $page == $serverName)
					return 0;
			}
		}
		
		return 1;
	}
	
	static function explodetrim($sep, $str){
		$ar = [];
		
		if($str){
			$ar = explode($sep, $str);
			
			if(is_array($ar)){
				foreach($ar as $k=>$ex){
					$ar[$k] = $ex = trim($ex);
					if($ex == '') unset($ar[$k]);
				}
				
				$ar = array_values($ar);
			}
		}
		
		return $ar;
	}
		static function explodeByEOL($str){
			return self::explodetrim(PHP_EOL, $str);
		}

	static function getExistJquery(){
		$jvers = 'jquery';

		$dir = $_SERVER['DOCUMENT_ROOT'].'/bitrix/js/main/jquery/';
		$files = scandir($dir);
		if(is_array($files)){ 
			foreach ($files as $file) {
				if(strpos($file, 'jquery-1') !== false){
					break;
				}

				if(strpos($file, 'jquery-2') !== false){
					$jvers = 'jquery2';
					break;
				}
				
				if(strpos($file, 'jquery-3') !== false){
					$jvers = 'jquery3';
					break;
				}
			}
		}

		return $jvers;
	}
	
	// append
	static function addBodyScript($script, $oldBuffer, $toEnd = 0){
		$search = '</body>';
		$replace = $script. PHP_EOL .$search;
		
		$bufferContent = $oldBuffer;
		
		if(substr_count($oldBuffer, $search) == 1){
			$bufferContent = str_replace($search, $replace, $oldBuffer);
		}else{
			$bodyEnd = self::getLastPositionIgnoreCase($oldBuffer, $search);
			if ($bodyEnd !== false){
				$bufferContent = substr_replace($oldBuffer, $replace, $bodyEnd, strlen($search));
			}elseif($toEnd){
				$bufferContent .= $script;
			}
		}
		
		return $bufferContent;
	}
	
	static function getLastPositionIgnoreCase($haystack, $needle, $offset = 0){
		if (defined("BX_UTF")){
			if (function_exists("mb_orig_strripos")){
				return mb_orig_strripos($haystack, $needle, $offset);
			}
			return mb_strripos($haystack, $needle, $offset, "latin1");
		}
		return strripos($haystack, $needle, $offset);
	}
	
	static function getFirstPositionIgnoreCase($haystack, $needle, $offset = 0){
		if (defined("BX_UTF")){
			if (function_exists("mb_orig_stripos")){
				return mb_orig_stripos($haystack, $needle, $offset);
			}
			return mb_stripos($haystack, $needle, $offset, "latin1");
		}
		return stripos($haystack, $needle, $offset);
	}

	// old no use
	static function checkModuleVersion($module, $version){
		$saleModuleInfo = \CModule::CreateModuleObject($module);
		return CheckVersion($saleModuleInfo->MODULE_VERSION, $version);
	}
}