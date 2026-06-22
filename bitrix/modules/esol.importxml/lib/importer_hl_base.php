<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ImporterHlBase {
	protected static $moduleId = 'esol.importxml';
	var $xmlParts = array();
	var $rcurrencies = array('#USD#', '#EUR#');
	var $getGetPartXmlObjects = array();
	
	public function ExecuteOnAfterSaveHandler($handler, $ID)
	{
		try{
			$command = $handler.';';
			eval($command);
		}catch(\Exception $ex){}
	}
	
	public function OnShutdown()
	{
		$arError = error_get_last();
		if(!is_array($arError) || !isset($arError['type']) || !in_array($arError['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) return;
		
		$this->EndWithError(sprintf(Loc::getMessage("ESOL_IX_FATAL_ERROR"), $arError['type'], $arError['message'], $arError['file'], $arError['line']));
	}
	
	public function HandleError($code, $message, $file, $line)
	{
		return true;
	}
	
	public function HandleException($exception)
	{
		if(is_callable(array('\Bitrix\Main\Diag\ExceptionHandlerFormatter', 'format')))
		{
			$this->EndWithError(\Bitrix\Main\Diag\ExceptionHandlerFormatter::format($exception));
		}
		$this->EndWithError(sprintf(Loc::getMessage("ESOL_IX_FATAL_ERROR"), '', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
	}
	
	public function EndWithError($error)
	{
		global $APPLICATION;
		$APPLICATION->RestartBuffer();
		ob_end_clean();
		$this->errors[] = $error;
		$this->SaveStatusImport();
		echo '<!--module_return_data-->'.(\Bitrix\EsolImportxml\Utils::PhpToJSObject($this->GetBreakParams()));
		die();
	}
}