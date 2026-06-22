<?php
namespace Yandex\Market\Catalog\Segment;

use Yandex\Market\Export\Param;

class CampaignConfig
{
	private $format;
	private $campaignId;
	private $campaignTitle;
	private $placementType;

	public function __construct(Param\Format $format, $campaignId, $title, $placementType = null)
	{
		$this->format = $format;
		$this->campaignId = (int)$campaignId;
		$this->campaignTitle = (string)$title;
		$this->placementType = $placementType !== null ? (string)$placementType : null;
	}

	public function campaignId()
	{
		return $this->campaignId;
	}

	public function campaignTitle()
	{
		return $this->campaignTitle;
	}

	public function placementType()
	{
		return $this->placementType;
	}

	public function format()
	{
		return $this->format;
	}
}