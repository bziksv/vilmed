<?php
namespace Yandex\Market\Type;

use Yandex\Market\Export\Xml\Data\CDataValue;

/** @noinspection PhpUnused */
class HtmlType extends StringType
{
	const DEFAULT_TAGS = '<br><p><ol><ul><li><div><h1><h2><h3><h4><h5><h6>';

    protected $settings = [
        'value_tags' => self::DEFAULT_TAGS,
        'max_length' => null,
    ];

    public function type()
    {
        return Manager::TYPE_HTML;
    }

	public function sanitize($value, array $context = [], array $settings = null)
	{
        if (!is_scalar($value)) { return null; }

        $tags = $this->setting('value_tags', $settings);
        $maxLength = $this->setting('max_length', $settings);

		$value = trim(strip_tags((string)$value, $tags));

        if ($value === '') { return null; }

		if (mb_strpos($value, '<') !== false) // has tags
		{
			$value = $this->stripTagAttributes($value);
			$value = $this->cutTagSpaces($value, $tags);
			$value = $this->replaceEditorSymbols($value);

			if ($maxLength !== null)
			{
				$textParser = new \CTextParser();
				$suffixLength = 3;

				$value = $textParser->html_cut($value, $maxLength - $suffixLength);
			}

			return new CDataValue($value);
		}

		$value = $this->replaceEditorSymbols($value);

		if ($maxLength !== null)
		{
			$value = $this->truncateText($value, $maxLength);
		}

		return $value;
	}

	/** @noinspection RegExpUnnecessaryNonCapturingGroup */
	protected function cutTagSpaces($contents, $tags)
	{
		$tags = str_replace('><', '|', trim($tags, '<>'));

		return preg_replace('#\s*(</?(?:' . $tags . ')\s*/?>)\s*#', '$1', $contents);
	}

	protected function replaceEditorSymbols($contents)
	{
		$contents = html_entity_decode($contents);
		$contents = str_replace(["\t", "<br>", "<div>", '</div>'], [" ", "<br />", "<br />", ""], $contents);

		return $contents;
	}

	protected function stripTagAttributes($contents)
	{
		return preg_replace('/<([a-z][a-z0-9]*)\s[^>]+?(\/?>)/i', '<$1$2', $contents);
	}
}