<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ImporterHlData extends ImporterHlBase {
	public function SaveRecordAdd($entityDataClass, $arFieldsElement)
	{
		$dbRes2 = $entityDataClass::Add($arFieldsElement, false, true, true);
		$ID = $dbRes2->GetID();
		
		if($ID)
		{
			//$this->SetTimeBegin($ID);
			$this->stepparams['element_added_line']++;
			$this->SaveElementId($ID);
			$this->SaveRecordAfter($ID);
		}
		else
		{
			$this->stepparams['error_line']++;
			$this->errors[] = sprintf(GetMessage("ESOL_IX_ADD_ELEMENT_ERROR"), implode(', ',$dbRes2->GetErrorMessages()), '');
			return false;
		}
		return true;
	}
	
	public function SaveRecordUpdate($entityDataClass, $ID, $arFieldsElement2)
	{
		$dbRes2 = $entityDataClass::Update($ID, $arFieldsElement2);
		if($dbRes2->isSuccess())
		{
			$this->SaveRecordAfter($ID);
		}
		else
		{
			$this->stepparams['error_line']++;
			$this->errors[] = sprintf(GetMessage("ESOL_IX_UPDATE_ELEMENT_ERROR"), implode(', ',$dbRes2->GetErrorMessages()), $ID);
		}
	}
}