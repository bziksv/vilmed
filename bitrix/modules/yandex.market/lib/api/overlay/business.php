<?php
namespace Yandex\Market\Api\Overlay;

use Yandex\Market\Api;
use Yandex\Market\Psr;
use Yandex\Market\Reference\Concerns;

class Business extends Api\Reference\Model
{
	use Concerns\HasOnce;

	private $auth;
	private $logger;

	public function __construct($businessId, Api\Reference\Auth $auth = null, Psr\Log\LoggerInterface $logger = null)
	{
		parent::__construct([ 'id' => $businessId ]);

		if ($auth === null)
		{
			list($auth, $logger) = Api\Reference\AuthRepository::byBusiness($businessId);
		}

		$this->auth = $auth;
		$this->logger = $logger;
	}

	public function getId()
	{
		return (int)$this->requireField('id');
	}

	/** @return Api\Campaigns\Model\CampaignCollection */
	public function getCampaigns()
	{
		return Api\Campaigns\Facade::campaigns($this->auth)->sameBusiness($this->getId());
	}

	/** @return Api\Business\Warehouses\Response */
	public function getWarehouses()
	{
		return $this->once('getWarehouses', function() {
			return (new Api\Business\Warehouses\Request($this->getId(), $this->auth, $this->logger))->execute();
		});
	}

	/** @return Api\Business\Settings\Model\Settings */
	public function getSettings()
	{
		return $this->once('getSettings', function() {
			return (new Api\Business\Settings\Request($this->getId(), $this->auth, $this->logger))->execute()->getSettings();
		});
	}
}