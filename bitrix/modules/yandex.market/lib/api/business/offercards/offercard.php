<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Yandex\Market\Api\Reference\Model;

class OfferCard extends Model
{
	public function getOfferId()
	{
		return (string)$this->requireField('offerId');
	}

	public function getMapping()
	{
		return $this->anyModel('mapping', Mapping::class);
	}

	public function getContentRating()
	{
		return (int)$this->getField('contentRating');
	}

	public function getErrors()
	{
		return $this->getCollection('errors', ErrorCollection::class);
	}

	public function getWarnings()
	{
		return $this->getCollection('warnings', ErrorCollection::class);
	}

	public function getRecommendations()
	{
		return $this->getCollection('recommendations', RecommendationCollection::class);
	}

	public function groupMessages()
	{
		$groups = [];
		$collections = [
			'errors' => $this->getErrors(),
			'warnings' => $this->getWarnings(),
			'recommendations' => $this->getRecommendations(),
		];

		foreach ($collections as $type => $collection)
		{
			$group = [];

			foreach ($collection as $message)
			{
				if ($message instanceof Error)
				{
					$group[] = [
						'message' => $message->getMessage(),
						'comment' => $message->getComment(),
					];
				}
				else if ($message instanceof Recommendation)
				{
					$comment = $message->typeComment();

					if ($comment === '') { continue; }

					$group[] = [
						'comment' => $comment,
					];
				}
			}

			if (empty($group)) { continue; }

			$groups[$type] = $group;
		}

		return $groups;
	}
}