<?php
namespace Yandex\Market\Trading\Service\Common;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Entity as TradingEntity;

/** @property Provider $provider */
abstract class Installer extends Market\Trading\Service\Reference\Installer
{
	use Market\Reference\Concerns\HasOnceStatic;

	public function install()
	{
		$this->installRoute();
		$this->installUserEnvironment();
		$this->installNotification();
		$this->pushApiMode();
	}

	protected function installRoute()
	{
		$context = $this->provider->getContext();

		if ((string)$context->getUrlId() === '') { return; }

		$context->getEnvironment()->getRoute()->installPublic($context->getSiteId());
	}

	protected function installUserEnvironment()
	{
		$group = $this->installUserGroup();
		$user = $this->installAnonymousUser();

		$this->attachUserToGroup($user, $group);
	}

	protected function installUserGroup()
	{
		$userGroup = $this->getUserGroup();

		if (!$userGroup->isInstalled())
		{
			$data = $this->getUserGroupData();
			$installResult = $userGroup->install($data);

			Market\Result\Facade::handleException($installResult);
		}

		return $userGroup;
	}

	protected function getUserGroup()
	{
		$context = $this->provider->getContext();
		$userGroupRegistry = $context->getEnvironment()->getUserGroupRegistry();

		return $userGroupRegistry->getGroup($this->provider->getServiceCode(), $context->getSiteId());
	}

	protected function getUserGroupData()
	{
		return $this->provider->getInfo()->getUserGroupData();
	}

	protected function installAnonymousUser()
	{
		$user = $this->getAnonymousUser();
		$user->checkInstall();

		if (!$user->isInstalled())
		{
			$data = $this->getAnonymousUserData();
			$installResult = $user->install($data);

			Market\Result\Facade::handleException($installResult);
		}

		return $user;
	}

	protected function getAnonymousUser()
	{
		$context = $this->provider->getContext();
		$userRegistry = $context->getEnvironment()->getUserRegistry();

		return $userRegistry->getAnonymousUser($this->provider->getServiceCode(), $context->getSiteId());
	}

	protected function getAnonymousUserData()
	{
		return $this->provider->getInfo()->getAnonymousUserData();
	}

	protected function attachUserToGroup(TradingEntity\Reference\User $user, TradingEntity\Reference\UserGroup $group)
	{
		$groupId = $group->getId();
		$attachResult = $user->attachGroup($groupId);

		Market\Result\Facade::handleException($attachResult);
	}

	protected function installNotification()
	{
		$context = $this->provider->getContext();
		$siteId = $context->getSiteId();
		$router = $this->provider->getRouter();
		$mailRepository = new Market\Ui\Trading\Notification\MailRepository();

		foreach ($router->getMap() as $path => $className)
		{
			$action = $router->getActionSample($path, $context->getEnvironment());

			if (!($action instanceof TradingService\Reference\Action\HasNotification)) { continue; }

			$notification = $action->getNotification();

			if ($mailRepository->search($notification, $siteId) !== null) { continue; }

			$mailRepository->make($notification, $siteId);
		}
	}

	public function uninstall(array $context = [])
	{
		$this->releaseApiMode();
	}

	public function migrate(TradingService\Reference\Provider $provider)
	{
		$this->migrateUserGroup($provider);
		$this->migrateAnonymousUser($provider);
		$this->migrateProfiles($provider);
	}

	protected function migrateUserGroup(TradingService\Reference\Provider $provider)
	{
		$userGroup = $this->getUserGroup();

		if (!$userGroup->isInstalled()) { return; }

		$code = $provider->getServiceCode();
		$data = $provider->getInfo()->getUserGroupData();

		$this->updateUserGroupCode($userGroup, $code);
		$this->updateUserGroupData($userGroup, $data);
	}

	protected function updateUserGroupCode(TradingEntity\Reference\UserGroup $userGroup, $code)
	{
		if ($code === $this->provider->getServiceCode()) { return; }

		$migrateResult = $userGroup->migrate($code);

		Market\Result\Facade::handleException($migrateResult);
	}

	protected function updateUserGroupData(TradingEntity\Reference\UserGroup $userGroup, $data)
	{
		if (empty($data)) { return; }

		$updateResult = $userGroup->update($data);

		Market\Result\Facade::handleException($updateResult);
	}

	protected function migrateAnonymousUser(TradingService\Reference\Provider $provider)
	{
		$user = $this->getAnonymousUser();

		if (!$user->isInstalled()) { return; }

		$code = $provider->getServiceCode();
		$data = $provider->getInfo()->getAnonymousUserData();

		$this->updateUserCode($user, $code);
		$this->updateUserData($user, $data);
	}

	protected function updateUserCode(TradingEntity\Reference\User $user, $code)
	{
		if ($code === $this->provider->getServiceCode()) { return; }

		$migrateResult = $user->migrate($code);

		Market\Result\Facade::handleException($migrateResult);
	}

	protected function updateUserData(TradingEntity\Reference\User $user, $data)
	{
		if (empty($data)) { return; }

		$updateResult = $user->update($data);

		Market\Result\Facade::handleException($updateResult);
	}

	protected function migrateProfiles(TradingService\Reference\Provider $provider)
	{
		$context = $this->provider->getContext();
		$environment = $context->getEnvironment();
		$profileValues = $provider->getInfo()->getProfileValues();
		$user = $this->getAnonymousUser();
		$profileEntity = $environment->getProfile();
		$personTypeEntity = $environment->getPaySystem();

		if (empty($profileValues) || !$user->isInstalled()) { return; }

		$userId = $user->getId();

		foreach ($personTypeEntity->getEnum($context->getSiteId()) as $personTypeOption)
		{
			foreach ($profileEntity->getEnum($userId, $personTypeOption['ID']) as $profileOption)
			{
				$updateResult = $profileEntity->update($profileOption['ID'], $profileValues);

				Market\Result\Facade::handleException($updateResult);
			}
		}
	}

	private function pushApiMode()
	{
		$command = $this->provider->getContainer()->get(Command\PushApiMode::class);
		$command->run();
	}

	private function releaseApiMode()
	{
		$campaignId = $this->provider->getContext()->getCampaign()->getId();
		$stateKey = "campaign_yandex_mode_{$campaignId}";

		Market\State::remove($stateKey);
	}
}