<?php
namespace Yandex\Market\Error;

use Yandex\Market\Reference\Concerns;

class XmlNode extends Base
{
    use Concerns\HasMessage { getMessage as protected getLocale; }

	protected $tagName;
	protected $attributeName;

    public static function createValidateEmpty()
    {
        return new static(self::getLocale('ERROR_EMPTY'), Base::XML_NODE_VALIDATE_EMPTY);
    }

	public function getUniqueKey()
	{
		return parent::getUniqueKey() . '|' . $this->tagName . '|' . $this->attributeName;
	}

	public function setTagName($tagName)
	{
		$this->tagName = $tagName;
	}

	public function getTagName()
	{
		return $this->tagName;
	}

	public function hasTagName()
	{
		return $this->tagName !== null;
	}

	public function setAttributeName($attributeName)
	{
		$this->attributeName = $attributeName;
	}

	public function getAttributeName()
	{
		return $this->attributeName;
	}

	public function hasAttributeName()
	{
		return $this->attributeName !== null;
	}

	/**
	 * @return string
	 */
	public function getMessage()
	{
		if ($this->hasAttributeName())
		{
			$result = self::getLocale('ATTRIBUTE', [
				'#ATTRIBUTE_NAME#' => $this->getAttributeName(),
				'#TAG_NAME#' => $this->getTagName(),
			]);
            $result .= $this->message;
		}
		else if ($this->hasTagName())
		{
			$result = self::getLocale('TAG', [ '#TAG_NAME#' => $this->getTagName() ]);
            $result .= $this->message;
		}
		else
		{
			$result = $this->message;
		}

		return $result;
	}
}