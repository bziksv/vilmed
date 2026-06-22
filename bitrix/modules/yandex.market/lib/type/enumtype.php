<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Export\Xml\Listing;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns as GlobalConcerns;

/** @noinspection PhpUnused */
class EnumType extends AbstractType
    implements Concerns\HasRecommendation
{
	use GlobalConcerns\HasMessage;

	const ERROR_TYPE = 'TYPE';
	const ERROR_INVALID = 'INVALID';

	protected $synonymCache = [];
    protected $settings = [
        'value_listing' => null,
	    'value_synonym' => true,
    ];

    public function __construct(Listing\Listing $listing = null, array $parameters = null)
    {
        if ($listing !== null)
        {
            if ($parameters === null) { $parameters = []; }

            $parameters['value_listing'] = $listing;
        }

        parent::__construct($parameters);
    }

    public function type()
    {
        return Manager::TYPE_ENUM;
    }

    public function recommendation(array $context = [])
    {
        $listing = $this->listing();

        return array_map(static function($value) use ($listing) {
            return [
                'VALUE' => $value,
                'DISPLAY' => $listing->display($value),
            ];
        }, $listing->values());
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		if (!is_string($value))
		{
            return new Error\XmlNode(
                self::getMessage('ERROR_' . self::ERROR_TYPE, [ '#TYPE#' => gettype($value) ]),
	            self::ERROR_TYPE
            );
		}

        $listing = $this->listing($settings);

		$casted = $this->cast($listing, $value, $settings);

		if ($casted === null)
		{
            return new Error\XmlNode(
                self::getMessage('ERROR_' . self::ERROR_INVALID, [
                    '#VALUE#' => $value,
                    '#AVAILABLE#' => $this->availableMessage($listing),
                ]),
	            self::ERROR_INVALID
            );
		}

		return $casted;
	}

	protected function cast(Listing\Listing $listing, $value, array $settings = null)
	{
		$builtIn = $this->searchBuiltIn($listing, $value);

		if ($builtIn !== null) { return $builtIn; }

		$migrated = $this->searchMigrated($listing, $value);

		if ($migrated !== null) { return $migrated; }

		if ($this->setting('value_synonym', $settings) !== false)
		{
			return $this->searchSynonym($listing, $value);
		}

		return null;
	}

    /** @return Listing\Listing */
    public function listing(array $settings = null)
    {
        $listing = $this->setting('value_listing', $settings);

        Assert::isInstanceOf($listing, Listing\Listing::class);

        return $listing;
    }

	protected function searchBuiltIn(Listing\Listing $listing, $value)
	{
		$variants = $listing->values();

		if (in_array($value, $variants, true))
		{
			return $value;
		}

		$valueLower = mb_strtolower(trim($value));

		foreach ($variants as $variant)
		{
			if ($valueLower === mb_strtolower($variant))
			{
				return $variant;
			}
		}

		return null;
	}

	protected function searchMigrated(Listing\Listing $listing, $value)
	{
		if (!($listing instanceof Listing\ListingWithMigration)) { return null; }

		return $listing->migrate($value);
	}

	protected function searchSynonym(Listing\Listing $listing, $value)
	{
		$result = null;

		foreach ($listing->values() as $variant)
		{
			if ($this->matchSynonym($listing, $variant, $value))
			{
				$result = $variant;
				break;
			}
		}

		return $result;
	}

	protected function matchSynonym(Listing\Listing $listing, $variant, $value)
	{
		$synonyms = $this->listingSynonyms($listing, $variant);
		$value = mb_strtolower($value);

		if (in_array($value, $synonyms, true)) { return true; }

        if (mb_strlen($value) <= 2)
        {
            return in_array($value . '.', $synonyms, true);
        }

		foreach ($synonyms as $synonym)
		{
			if (mb_strpos($value, $synonym) !== false)
			{
				return true;
			}
		}

		return false;
	}

	protected function listingSynonyms(Listing\Listing $listing, $variant)
	{
		if (!isset($this->synonymCache[$variant]))
		{
			$synonyms = $listing->synonyms($variant);

			if (empty($synonyms)) { $synonyms = [ $listing->display($variant) ]; }

			$this->synonymCache[$variant] = array_map(
				static function($synonym) { return mb_strtolower($synonym); },
				$synonyms
			);
		}

		return $this->synonymCache[$variant];
	}

    protected function availableMessage(Listing\Listing $listing)
    {
        $message = null;
        $index = 0;

        foreach ($listing->values() as $value)
        {
            if (++$index > 10)
            {
                $message .= '...';
                break;
            }

            if ($message === null)
            {
                $message = $value;
                continue;
            }

            $message .= ', ' . $value;
        }

        return $message;
    }
}