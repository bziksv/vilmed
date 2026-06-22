<?php

namespace Yandex\Market\Api\OAuth2\RefreshToken;

use Bitrix\Main;
use Yandex\Market;

class Agent extends Market\Reference\Agent\Regular
{
	use Market\Reference\Concerns\HasLang;

	const REFRESH_DAY_GAP = 3; // force refresh token before days

	const NOTIFY_REFRESH_FAIL = 'TOKEN_REFRESH_FAIL';

	protected static function includeMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
	}

	public static function getDefaultParams()
	{
		return [
			'interval' => 86400,
		];
	}

	public static function getRefreshLimit()
	{
		return (int)Market\Config::getOption('refresh_token_limit', 10);
	}

	public static function schedule()
	{
		$result = new Market\Result\Base();
		$nearestDate = static::getNearestDateWithGap();

		if ($nearestDate)
		{
			static::register([
				'next_exec' => $nearestDate,
			]);
		}
		else
		{
			$message = static::getLang('API_REFRESH_TOKEN_NOT_FOUND_TOKEN_EXPIRE');
			$result->addError(new Market\Error\Base($message));
		}

		return $result;
	}

	public static function run()
	{
		global $pPERIOD;

		$isNeedRepeat = true;

		static::processTokens();
		$nearestDate = static::getNearestDateWithGap();

		if ($nearestDate)
		{
			$pPERIOD = max(60, $nearestDate->getTimestamp() - time());
		}
		else
		{
			$isNeedRepeat = false;
		}

		return $isNeedRepeat;
	}

	protected static function processTokens()
	{
		$tokenList = Market\Api\OAuth2\Token\Model::loadList([
			'filter' => [
				'<=EXPIRES_AT' => static::getProcessGapDate(),
				'<=REFRESH_COUNT' => static::getRefreshLimit(),
			],
		]);

		foreach ($tokenList as $token)
		{
            $business = static::getBusiness($token);

			if ($business !== null)
			{
				static::refreshToken($token, $business);
			}
			else // not used
			{
				static::deleteToken($token);
			}
		}
	}

	protected static function getBusiness(Market\Api\OAuth2\Token\Model $token)
	{
		return Market\Trading\Business\Model::loadOne([
			'filter' => [
				'=OAUTH_CLIENT_ID.VALUE' => $token->getClientId(),
				'=OAUTH_TOKEN.VALUE' => $token->getId(),
				'API_KEY.VALUE' => false,
			],
			'runtime' => [
				new Main\Entity\ReferenceField('OAUTH_CLIENT_ID', Market\Trading\Settings\Table::class, [
					'=ref.ENTITY_TYPE' => [ '?', Market\Trading\Settings\Table::ENTITY_TYPE_BUSINESS ],
					'=ref.ENTITY_ID' => 'this.ID',
					'=ref.NAME' => [ '?', 'OAUTH_CLIENT_ID' ],
				]),
				new Main\Entity\ReferenceField('OAUTH_TOKEN', Market\Trading\Settings\Table::class, [
					'=ref.ENTITY_TYPE' => [ '?', Market\Trading\Settings\Table::ENTITY_TYPE_BUSINESS ],
					'=ref.ENTITY_ID' => 'this.ID',
					'=ref.NAME' => [ '?', 'OAUTH_TOKEN' ],
				]),
				new Main\Entity\ReferenceField('API_KEY', Market\Trading\Settings\Table::class, [
					'=ref.ENTITY_TYPE' => [ '?', Market\Trading\Settings\Table::ENTITY_TYPE_BUSINESS ],
					'=ref.ENTITY_ID' => 'this.ID',
					'=ref.NAME' => [ '?', 'API_KEY' ],
				]),
			],
		]);
	}

	protected static function refreshToken(Market\Api\OAuth2\Token\Model $token, Market\Trading\Business\Model $business)
	{
		$requestResult = static::requestToken($token, $business);

		if ($requestResult->isSuccess())
		{
			/** @var Response $response */
			$response = $requestResult->getResponse();
			$lastResult = static::updateToken($token, $response);
		}
		else
		{
			$lastResult = $requestResult;
		}

		if (!$lastResult->isSuccess())
		{
			$token->incrementRefreshCount();
			static::writeTokenError($token, $lastResult);

			if (!$token->canRefresh())
			{
				static::addLogError($lastResult, $business->createLogger());
				static::notifyUserError($business);
			}
		}

		return $lastResult;
	}

	protected static function deleteToken(Market\Api\OAuth2\Token\Model $token)
	{
		return Market\Api\OAuth2\Token\Table::delete($token->getId());
	}

	protected static function requestToken(Market\Api\OAuth2\Token\Model $token, Market\Trading\Business\Model $business)
	{
		$request = new Request();
		$request->setOauthClientId($business->getOptions()->getValue('OAUTH_CLIENT_ID'));
		$request->setOauthClientPassword($business->getOptions()->getValue('OAUTH_CLIENT_PASSWORD'));
		$request->setRefreshToken($token->getRefreshToken());

		return $request->send();
	}

	protected static function updateToken(Market\Api\OAuth2\Token\Model $token, Response $response)
	{
		return Market\Api\OAuth2\Token\Table::update($token->getId(), [
			'TOKEN_TYPE' => $response->getTokenType(),
			'ACCESS_TOKEN' => $response->getAccessToken(),
			'REFRESH_TOKEN' => $response->getRefreshToken(),
			'EXPIRES_AT' => $response->getExpiresDate(),
			'REFRESH_COUNT' => 0,
			'REFRESH_MESSAGE' => '',
		]);
	}

	/**
	 * @param Market\Api\OAuth2\Token\Model $token
	 * @param Main\Result|Market\Result\Base $result
	 *
	 * @return Main\Entity\UpdateResult
	 */
	protected static function writeTokenError(Market\Api\OAuth2\Token\Model $token, $result)
	{
		$message = implode('; ', $result->getErrorMessages());

		return Market\Api\OAuth2\Token\Table::update($token->getId(), [
			'REFRESH_COUNT' => $token->getRefreshCount(),
			'REFRESH_MESSAGE' => $message,
		]);
	}

	/**
	 * @param Main\Result|Market\Result\Base $result
	 * @param Market\Psr\Log\LoggerInterface $logger
	 */
	protected static function addLogError($result, Market\Psr\Log\LoggerInterface $logger)
	{
		$errorMessage = implode('; ', $result->getErrorMessages());
		$logMessage = static::getLang('API_REFRESH_TOKEN_LOG_ERROR', [ '#MESSAGE#' => $errorMessage ], $errorMessage);

		$logger->error($logMessage, [
			'AUDIT' => Market\Logger\Trading\Audit::PROCEDURE,
		]);
	}

	protected static function notifyUserError(Market\Trading\Business\Model $business)
	{
		$editUrl = Market\Ui\Admin\Path::getModuleUrl('trading_list', [
			'lang' => LANGUAGE_ID,
			'business' => $business->getId(),
		]);
		$logUrl = Market\Ui\Admin\Path::getModuleUrl('trading_log', [
			'lang' => LANGUAGE_ID,
			'business' => $business->getId(),
			'find_level' => Market\Logger\Level::ERROR,
			'set_filter' => 'Y',
			'apply_filter' => 'Y',
		]);
		$message = static::getLang('API_REFRESH_TOKEN_NOTIFY_FAIL_REFRESH', [
			'#LOG_URL#' => $logUrl,
			'#EDIT_URL#' => $editUrl,
		]);

		\CAdminNotify::Add([
			'MODULE_ID' => Market\Config::getModuleName(),
			'TAG' => static::NOTIFY_REFRESH_FAIL,
			'NOTIFY_TYPE' => \CAdminNotify::TYPE_ERROR,
			'MESSAGE' => $message,
		]);
	}

	/**
	 * @return Main\Type\DateTime|null
	 */
	protected static function getNearestDateWithGap()
	{
		$nearestDate = static::getNearestDate();

		if ($nearestDate !== null)
		{
			$refreshGap = static::getRefreshDateGap($nearestDate);

			if ($refreshGap !== null)
			{
				$nearestDate->add('-' . $refreshGap);
			}
		}

		return $nearestDate;
	}

	/**
	 * @return Main\Type\DateTime|null
	 */
	protected static function getNearestDate()
	{
		$result = null;

		$query = Market\Api\OAuth2\Token\Table::getList([
			'order' => [ 'EXPIRES_AT' => 'ASC' ],
			'limit' => 1,
			'select' => [ 'EXPIRES_AT' ],
		]);

		while ($row = $query->fetch())
		{
			$result = $row['EXPIRES_AT'];
		}

		return $result;
	}

	/**
	 * @param Main\Type\DateTime $bitrixDate
	 * @return string|null
	 */
	protected static function getRefreshDateGap(Main\Type\DateTime $bitrixDate)
	{
		$now = new \DateTime();
		$date = new \DateTime();
		$date->setTimestamp($bitrixDate->getTimestamp());
		$interval = $date->diff($now);
		$days = (int)$interval->format('%a');
		$result = null;

		if ($days < 0)
		{
			// no gap, immediate
		}
		else if ($days === 0)
		{
			if ($interval->h > 1)
			{
				$result = 'PT1H';
			}
			else if ($interval->m > 1)
			{
				$result = 'PT1M';
			}
		}
		else if ($days === 1)
		{
			$result = 'PT12H';
		}
		else
		{
			$dayGap = min($days, static::REFRESH_DAY_GAP);

			$result = 'P' . $dayGap . 'D';
		}

		return $result;
	}

	protected static function getProcessGapDate()
	{
		$result = new Main\Type\DateTime();
		$result->add('P' . static::REFRESH_DAY_GAP. 'D');

		return $result;
	}
}