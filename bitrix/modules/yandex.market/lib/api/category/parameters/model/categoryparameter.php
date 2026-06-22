<?php
/** @noinspection PhpUnused */
namespace Yandex\Market\Api\Category\Parameters\Model;

use Yandex\Market\Api\Reference\Model;
use Yandex\Market\Reference\Concerns;

class CategoryParameter extends Model
{
	use Concerns\HasMessage;

	const TYPE_TEXT = 'TEXT';
	const TYPE_ENUM = 'ENUM';
	const TYPE_NUMERIC = 'NUMERIC';
	const TYPE_BOOLEAN = 'BOOLEAN';

	const ID_OTHER = 57046341;

	public function getId()
	{
		return (int)$this->requireField('id');
	}

	public function isAllowCustomValues()
	{
		return (bool)$this->requireField('allowCustomValues');
	}

	public function isDistinctive()
	{
		return (bool)$this->requireField('distinctive');
	}

	public function isFiltering()
	{
		return (bool)$this->requireField('filtering');
	}

	public function isMultiple()
	{
		return (bool)$this->requireField('multivalue');
	}

	public function isRequired()
	{
		return (bool)$this->requireField('required');
	}

	public function getType()
	{
		return (string)$this->requireField('type');
	}

	public function getName()
	{
		return (string)$this->getField('name');
	}

	public function getDescription()
	{
		return (string)$this->getField('description');
	}

	public function getConstraints()
	{
		return $this->getField('constraints');
	}

	public function getRecommendationTypes()
	{
		return $this->getField('recommendationTypes');
	}

	public function getFullDescription()
	{
		$description = $this->getDescription();
		$recommendations = $this->getRecommendationsDescription();

		if (!empty($description) && !empty($recommendations))
		{
			return sprintf('%s<br><br>%s', $description, $recommendations);
		}

		if (empty($recommendations))
		{
			return $description;
		}

		if (empty($description))
		{
			return $recommendations;
		}

		return '';
	}

	protected function getRecommendationsDescription() {
		$recommendations = $this->getRecommendationTypes();
		if (!isset($recommendations) || !is_array($recommendations)) { return ''; }

		$result = [];
		foreach ($recommendations as $type)
		{
			$description = self::getMessage('RECOMMENDATION_' . $type, null, 'NOT_FOUND');
			if ($description === 'NOT_FOUND') { continue; }

			$result[] = sprintf('<li>%s</li>', $description);
		}

		if (empty($result)) { return ''; }

		return sprintf('%s<br><ul>%s</ul>', self::getMessage('RECOMMENDATION_TITLE'), implode($result));
	}

    /** @return array{id: int, value: string, description: ?string}[]*/
	public function getValues()
	{
		return $this->getField('values');
	}

    public function getValueRestrictions()
    {
        return $this->getCollection('valueRestrictions', ValueRestrictionCollection::class);
    }

    public function getUnit()
    {
        return $this->getModel('unit', Unit::class);
    }
}