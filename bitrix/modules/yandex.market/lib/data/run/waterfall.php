<?php
namespace Yandex\Market\Data\Run;

class Waterfall
{
	/** @var callable[] */
	private $stages = [];
	private $index = 0;
	private $nextArguments;

	public function add(callable $stage)
	{
		$this->stages[] = $stage;

		return $this;
	}

	public function __invoke(Waterfall $waterfall, ...$arguments)
	{
		$this->next(...$arguments);

		if ($this->nextArguments !== null)
		{
			$waterfall->next(...$this->nextArguments);
		}
		else
		{
			$waterfall->next();
		}
	}

	public function run(...$arguments)
	{
		$this->index = 0;
		$this->next(...$arguments);
	}

	public function next(...$arguments)
	{
		if (!isset($this->stages[$this->index]))
		{
			$this->nextArguments = $arguments;
			return;
		}

		$stage = $this->stages[$this->index];

		++$this->index;
		/** @noinspection VariableFunctionsUsageInspection */
		call_user_func($stage, $this, ...$arguments);
		--$this->index;
	}
}