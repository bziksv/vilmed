<?php
namespace Yandex\Market\Trading\Service\Common\Command;

use Yandex\Market\State;
use Yandex\Market\Trading\Campaign\Placement;
use Yandex\Market\Utils;
use Yandex\Market\Trading\Service\Marketplace\Api;
use Yandex\Market\Trading\Service\Marketplace\Provider;

class PushApiMode
{
	protected $provider;

	public function __construct(Provider $provider)
	{
		$this->provider = $provider;
    }

	public function run($mode = null)
	{
		$campaign = $this->provider->getContext()->getCampaign();

		if ($campaign->getPlacement() === Placement::FBY) { return; }

		if ($mode === null)
		{
			$mode = $this->provider->getOptions()->getYandexMode();
		}

		$campaignId = $campaign->getId();
		$stateKey = "campaign_yandex_mode_{$campaignId}";

		if ((string)$mode === '' || State::get($stateKey) === $mode) { return; }

		Utils\ServerStamp\Facade::check();

		$request = $this->provider->getRequestFactory()->create(Api\ApiMode\Request::class);
		$request->setApiMode(mb_strtoupper($mode));
        $request->execute();

		State::set($stateKey, $mode);
	}
}