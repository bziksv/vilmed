<?php
namespace Yandex\Market\Component\Catalog\Molecules;

use Yandex\Market\Utils;

class SegmentEnable
{
    private $map;

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function extendFields(array $fields)
    {
        foreach ($this->map as $enableGroup => $segmentGroup)
        {
            $enableKey = $this->groupKey($fields, $enableGroup);
            $segmentKeys = $this->groupKeys($fields, $segmentGroup);

			if ($enableKey === null) { continue; }

            foreach ($segmentKeys as $segmentFieldKey)
            {
                $fields[$enableKey]['HIDDEN'] = 'Y';
                $fields[$segmentFieldKey]['ENABLE_FIELD'] = &$fields[$enableKey];
            }
        }

        return $fields;
    }

	private function groupKey(array $fields, $group)
	{
		$keys = $this->groupKeys($fields, $group);

		return !empty($keys) ? reset($keys) : null;
	}

    private function groupKeys(array $fields, $group)
    {
        $result = [];
        $groupParts = Utils\Field::splitKey($group);
        $groupLast = array_pop($groupParts);

        foreach ($fields as $key => $field)
        {
            $fieldGroup = isset($field['FIELD_GROUP']) ? $field['FIELD_GROUP'] : $field['FIELD_NAME'];

            if ($fieldGroup !== $group) { continue; }

            $nameParts = Utils\Field::splitKey($field['FIELD_NAME'], Utils\Field::GLUE_BRACKET);
            $nameLast = array_pop($nameParts);

            if ($nameLast !== $groupLast) { continue; }

            $result[$this->groupIndex($nameParts, $groupParts)] = $key;
        }

        return $result;
    }

    private function groupIndex(array $nameParts, array $groupParts)
    {
        $groupIndex = 0;
        $nextIndex = false;
        $indexes = [];

        foreach ($nameParts as $namePart)
        {
            if ($nextIndex)
            {
                $indexes[] = $namePart;
                ++$groupIndex;
                continue;
            }

            if ($namePart === $groupParts[$groupIndex])
            {
                $nextIndex = true;
                continue;
            }

            $warning = sprintf(
                'cant match %s and %s',
                implode('.', $nameParts),
                implode('.', $groupParts)
            );
            trigger_error($warning, E_USER_WARNING);

            return -1;
        }

        return !empty($indexes) ? implode('.', $indexes) : 0;
    }
}