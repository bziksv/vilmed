<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Yandex\Market\Export;

/** @var array $arParams */
/** @var array $arResult */

// check filled

foreach ($arParams['GROUPS'] as &$group)
{
    if (!is_array($group['VALUE'])) { $group['VALUE'] = []; }

    foreach ($group['VALUE'] as $tagValue)
    {
        if (!empty($tagValue) && empty($tagValue['PLACEHOLDER']))
        {
            return;
        }
    }
}
unset($group);

// preselect

$context = is_array($arParams['CONTEXT']) ? $arParams['CONTEXT'] : [];

foreach ($arParams['GROUPS'] as &$group)
{
    /** @var Export\Xml\Tag\Base $tag */
    foreach ($group['TAGS'] as $tagId => $tag)
    {
        $nodes = array_merge([ $tag ], $tag->getAttributes());
        $valueGroups = [];

        foreach ($nodes as $node)
        {
			if ($node->getParameter('preselect') === true)
			{
				$needPreselect = true;
			}
			else if ($tag->isRequired() && $node->isRequired())
			{
				$idChain = explode('.', $tagId);
				$needPreselect = true;

				do
				{
					array_pop($idChain);

					if (empty($idChain)) { break; }

					$parentId = implode('.', $idChain);

					if (!isset($group['TAGS'][$parentId]))
					{
						$needPreselect = false;
						break;
					}

					/** @var Export\Xml\Tag\Base $parentTag */
					$parentTag = $group['TAGS'][$parentId];

					if (!$parentTag->isRequired() && $parentTag->getParameter('preselect') !== true)
					{
						$needPreselect = false;
						break;
					}
				}
				while (true);
			}
			else
			{
				$needPreselect = false;
			}

            if (!$needPreselect) { continue; }

            $values = $node->preselect($context);

            if ($values === null) { continue; }

            if (isset($values['TYPE'])) { $values = [ $values ]; } // convert single format to multiple

            foreach ($values as $index => $value)
            {
                if (!isset($value['TYPE'], $arResult['SOURCE_TYPE_ENUM'][$value['TYPE']])) { continue; }

                $type = $arResult['SOURCE_TYPE_ENUM'][$value['TYPE']];
                $recommendationId = ($node !== $tag ? $tagId . '.' . $node->getId() : $tagId);
                $fields = [
                    'XML_TYPE' => Export\ParamValue\Table::XML_TYPE_VALUE,
                ];

                if ($node !== $tag) // attribute
                {
                    $fields = [
                        'XML_TYPE' => Export\ParamValue\Table::XML_TYPE_ATTRIBUTE,
                        'XML_ATTRIBUTE_NAME' => $node->getId(),
                    ];

                    $recommendationId .= '.' . $node->getId();
                }

                if ($type['CONTROL'] === Export\Entity\Manager::CONTROL_TEXT)
                {
                    $fields += [
                        'SOURCE_TYPE' => $value['TYPE'],
                        'SOURCE_FIELD' => $value['VALUE'],
                    ];

                    $recommendationValue = $value['TYPE'] . '|' . $value['VALUE'];
                }
                else if ($type['CONTROL'] === Export\Entity\Manager::CONTROL_FORMULA)
                {
                    $formulaSource = Export\Entity\Manager::getSource($value['TYPE']);

                    if (!($formulaSource instanceof Export\Entity\Reference\HasFieldCompilation)) { continue; }

                    $fields += [
                        'SOURCE_TYPE' => $value['TYPE'],
                        'SOURCE_FIELD' => $value['FIELD'],
                    ];

                    $recommendationValue = $value['TYPE'] . '|' . $formulaSource->compileField($value['FIELD']);
                }
                else
                {
                    $fields += [
                        'SOURCE_TYPE' => $value['TYPE'],
                        'SOURCE_FIELD' => (string)$value['FIELD'],
                    ];

                    $recommendationValue = $value['TYPE'] . '|' . $value['FIELD'];
                }

                if (isset($arResult['RECOMMENDATION'][$recommendationId]))
                {
                    foreach ($arResult['RECOMMENDATION'][$recommendationId] as $recommendation)
                    {
                        if ($recommendation['ID'] === $recommendationValue)
                        {
                            $fields['SOURCE_TYPE'] = Export\ParamValue\Table::SOURCE_TYPE_RECOMMENDATION;
                            $fields['SOURCE_FIELD'] = $recommendationValue;
                            break;
                        }
                    }
                }

                if (!isset($valueGroups[$index]))
                {
                    $valueGroups[$index] = [];
                }

                $valueGroups[$index][] = $fields;
            }
        }

        if (empty($valueGroups)) { continue; }

        $parentChain = explode('.', $tagId);
        $selfName = array_pop($parentChain);
        $parentLevel = &$group['VALUE'];

        foreach ($parentChain as $parentId)
        {
            $hasParentValue = false;

            foreach ($parentLevel as $valueIndex => &$valueGroup)
            {
                if ($valueGroup['XML_TAG'] !== $parentId) { continue; }

                if (!isset($valueGroup['CHILDREN'])) { $valueGroup['CHILDREN'] = []; }

                $hasParentValue = true;
                $parentLevel = &$valueGroup['CHILDREN'];
                break;
            }
            unset($valueGroup);

            if ($hasParentValue) { continue; }

            $valueGroup = [
                'XML_TAG' => $parentId,
                'PARAM_VALUE' => [],
                'CHILDREN' => [],
            ];

            $parentLevel[] = &$valueGroup;
            $parentLevel = &$valueGroup['CHILDREN'];
            unset($valueGroup);
        }

	    $settingDefaults = [];

	    foreach ((array)$tag->getSettingsDescription($context) as $settingName => $setting)
	    {
		    if (!isset($setting['DEFAULT_VALUE'])) { continue; }

		    $settingDefaults[$settingName] = $setting['DEFAULT_VALUE'];
	    }

        foreach ($valueGroups as $valueGroup)
        {
			$tagValue = [
				'XML_TAG' => $selfName,
				'PARAM_VALUE' => $valueGroup,
			];

			if (!empty($settingDefaults))
			{
				$tagValue['SETTINGS'] = $settingDefaults;
			}

            $parentLevel[] = $tagValue;
        }

        unset($parentLevel);
    }
}
unset($group);
