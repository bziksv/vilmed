<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Type;
use Yandex\Market\Export\Entity;
use Bitrix\Main;

class Url extends Base
{
    protected $utmKeys = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'utm_custom',
    ];

	public function getDefaultParameters()
	{
		return [
			'name' => 'url',
			'value_type' => Type\Manager::TYPE_URL,
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
			[
				'TYPE' => Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD,
				'FIELD' => 'DETAIL_PAGE_URL',
			],
			[
				'TYPE' => Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD,
				'FIELD' => 'CANONICAL_PAGE_URL',
			],
            [
                'TYPE' => Entity\Manager::TYPE_IBLOCK_OFFER_FIELD,
                'FIELD' => 'DETAIL_PAGE_URL',
            ],
            [
                'TYPE' => Entity\Manager::TYPE_IBLOCK_OFFER_FIELD,
                'FIELD' => 'CANONICAL_PAGE_URL',
            ]
		];
	}

    public function sanitize($value, array $context = [], array $tagValue = null, array $siblingsValues = null)
    {
        $value = parent::sanitize($value, $context, $tagValue, $siblingsValues);

        if (!is_string($value) || $value === '') { return $value; }

        $query = $this->utmQueryString($tagValue);

        return $this->injectUrlQuery($value, $query);
    }

    protected function utmQueryString(array $tagValue = null)
    {
        if (empty($tagValue['SETTINGS'])) { return ''; }

        $query = [];

        foreach ($this->utmKeys as $utmKey)
        {
            $utmField = mb_strtoupper($utmKey);

            if (!isset($tagValue['SETTINGS'][$utmField]) || !is_string($tagValue['SETTINGS'][$utmField])) { continue; }

            $utmValue = trim($tagValue['SETTINGS'][$utmField]);

            if ($utmValue === '') { continue; }

            $query[$utmKey] = $utmValue;
        }

        if (empty($query)) { return ''; }

        return $this->buildQueryParams($query);
    }

    protected function injectUrlQuery($url, $queryString)
	{
        if ($queryString === '') { return $url; }

        $anchor = mb_strpos($url, '#');
        $sliced = '';

        if ($anchor !== false)
        {
            $sliced = mb_substr($url, $anchor);
            $url = mb_substr($url, 0, $anchor);
        }

        return
            $url
            . (mb_strpos($url, '?') === false ? '?' : '&')
            . $queryString
            . $sliced;
	}

	public function getSettingsDescription(array $context = [])
	{
        $result = [];

        foreach ($this->utmKeys as $key)
        {
            $result[mb_strtoupper($key)] = [
                'TITLE' => $key,
                'TYPE' => 'param',
            ];
        }

		return $result;
	}

    /** @noinspection PhpDeprecationInspection */
    protected function buildQueryParams($queryParams)
	{
		if (!Main\Application::isUtfMode())
		{
			$queryParams = Main\Text\Encoding::convertEncodingArray($queryParams, LANG_CHARSET, 'UTF-8');
		}

		return http_build_query($queryParams, null, '&', PHP_QUERY_RFC3986);
	}
}
