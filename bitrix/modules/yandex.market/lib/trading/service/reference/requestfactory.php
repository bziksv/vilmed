<?php
namespace Yandex\Market\Trading\Service\Reference;

use Bitrix\Main\ArgumentException;
use Yandex\Market\Api;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Trading\Setup\CampaignContext;

class RequestFactory
{
	protected $provider;

	public function __construct(Provider $provider)
	{
		$this->provider = $provider;
	}

	/**
	 * @template T of Api\Reference\Request
	 * @param class-string<T> $requestClass
	 *
	 * @return T
	 */
	public function create($requestClass)
	{
		$logger = $this->provider->getLogger();
		$context = $this->provider->getContext();

		if (is_subclass_of($requestClass, Api\Partner\Reference\BusinessRequest::class))
		{
			$business = $context->getBusiness();

			return new $requestClass($business->getId(), $business->getOptions()->getApiAuth(), $logger);
		}

		if (is_subclass_of($requestClass, Api\Partner\Reference\Request::class))
		{
			/** @var CampaignContext $context */
			Assert::isInstanceOf($context, CampaignContext::class);

			return new $requestClass($context->getCampaign()->getId(), $context->getBusiness()->getOptions()->getApiAuth(), $logger);
		}

		if (is_subclass_of($requestClass, Api\Reference\RequestTokenized::class))
		{
			return new $requestClass($context->getBusiness()->getOptions()->getApiAuth(), $logger);
		}

		if (is_subclass_of($requestClass, Api\Reference\Request::class))
		{
			return new $requestClass($logger);
		}

		throw new ArgumentException(sprintf('%s must be subclass of %s', $requestClass, Api\Reference\Request::class));
	}
}