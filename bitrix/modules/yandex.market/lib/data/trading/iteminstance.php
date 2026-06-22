<?php
namespace Yandex\Market\Data\Trading;

class ItemInstance
{
	public static function map(array $aInstances, array $bInstances)
	{
		$found = [];

		foreach ($aInstances as $aInstanceKey => $aInstance)
		{
			$foundKey = null;

			foreach ($aInstance as $type => $aValue)
			{
				if (!is_scalar($aValue) || trim($aValue) === '') { continue; }

				$typeUpper = mb_strtoupper($type);

				foreach ($bInstances as $bInstanceKey => $bInstance)
				{
					if (!isset($bInstance[$type])) { continue; }

					$bValue = $bInstance[$type];

					if (!is_scalar($bValue) || trim($bValue) === '') { continue; }

					if ($typeUpper === 'CIS')
					{
						$isSame = Cis::isSame($aValue, $bValue);
					}
					else if ($typeUpper === 'UIN')
					{
						$isSame = Uin::isSame($aValue, $bValue);
					}
					else
					{
						$isSame = (mb_strtolower(trim($aValue)) === mb_strtolower(trim($bValue)));
					}

					if ($isSame)
					{
						$foundKey = $bInstanceKey;
						break;
					}
				}

				if ($foundKey !== null) { break; }
			}

			if ($foundKey === null) { continue; }

			$found[$aInstanceKey] = $foundKey;
		}

		return $found;
	}

	public static function merge(array $aInstances, array $bInstances, array $keyMap = null)
	{
		foreach ($aInstances as $aKey => &$aInstance)
		{
			if (!is_array($aInstance)) { continue; }

			if ($keyMap !== null)
			{
				if (!isset($keyMap[$aKey])) { continue; }

				$bKey = $keyMap[$aKey];
			}
			else
			{
				$bKey = $aKey;
			}

			if (!isset($bInstances[$bKey]) || !is_array($bInstances[$bKey])) { continue; }

			$aInstance += $bInstances[$bKey];
 		}
		unset($aInstance);

		return $aInstances;
	}
}