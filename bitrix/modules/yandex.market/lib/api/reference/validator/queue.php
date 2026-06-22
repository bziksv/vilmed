<?php
namespace Yandex\Market\Api\Reference\Validator;

class Queue
{
	/** @var Validator[] */
	protected $queue = [];

	public function add(Validator $validator)
	{
		$this->queue[] = $validator;

		return $this;
	}

	public function handle($data, $httpStatus)
	{
		foreach ($this->queue as $validator)
		{
			$validator->check($data, $httpStatus);
		}
	}
}