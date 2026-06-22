<?php
namespace Yandex\Market\Trading\Service\Reference;

abstract class Installer
{
	protected $provider;

	public function __construct(Provider $provider)
	{
		$this->provider = $provider;
	}

	abstract public function install();

	abstract public function uninstall(array $context = []);

	public function migrate(Provider $provider)
	{
		// nothing by default
	}

	public function onCatalogSubmitted()
	{
		// nothing by default
	}
}