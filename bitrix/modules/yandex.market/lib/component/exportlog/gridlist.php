<?php
namespace Yandex\Market\Component\ExportLog;

use Yandex\Market\Component;
use Yandex\Market\Reference\Concerns;

class GridList extends Component\Data\GridList
{
	use Concerns\HasMessage;

	private $calculatedFields;

	public function __construct(\CBitrixComponent $component, array $componentParameters = [])
	{
		parent::__construct($component, $componentParameters);

		self::includeSelfMessages();

		$this->calculatedFields = new Component\Molecules\CalculatedFields([
			'TRACE' => [
				'TYPE' => 'trace',
				'ITEM_LOADER' => function(array $row) {
					return isset($row['CONTEXT']['TRACE']) ? $row['CONTEXT']['TRACE'] : null;
				},
				'USES' => [ 'CONTEXT' ],
			],
		], self::getMessagePrefix());
	}

	public function getFields(array $select = [])
	{
		$fields = parent::getFields($select);
		$fields += $this->calculatedFields->getFields();

		return $fields;
	}

	public function load(array $queryParameters = [])
	{
		list($queryParameters, $calculated) = $this->calculatedFields->queryParameters($queryParameters);

		$rows = parent::load($queryParameters);
		$rows = $this->calculatedFields->extendRows($rows, $calculated);

		return $rows;
	}
}