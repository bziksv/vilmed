<?php
namespace Yandex\Market\Api\Reference;

use Yandex\Market\Psr;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading;

class AuthRepository
{
	use Concerns\HasOnceStatic;

	/** @return array{Auth, Psr\Log\LoggerInterface} */
	public static function any()
	{
		return self::onceStatic('any', static function() {
			$business = Trading\Business\Table::getRow([
				'filter' => [
					[
						'LOGIC' => 'OR',
						[ 'SETTINGS.NAME' => 'API_KEY', '!SETTINGS.VALUE' => false ],
						[ 'SETTINGS.NAME' => 'OAUTH_TOKEN', '!SETTINGS.VALUE' => false ],
					],
				],
				'select' => [ 'ID' ],
			]);

			Assert::notNull($business, 'business');

			return self::byBusiness($business['ID']);
		});
	}

	/**
	 * @param int $id
	 * @return array{Auth, Psr\Log\LoggerInterface}
	 */
	public static function byBusiness($id)
	{
		return self::onceStatic('byBusiness', [ $id ], static function($id) {
			$business = Trading\Business\Model::loadById($id);

			return [
				$business->getOptions()->getApiAuth(),
				$business->createLogger(),
			];
		});
	}
}