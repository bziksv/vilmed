<?php
namespace Yandex\Market\Result;

use Yandex\Market\Export\Xml;
use Yandex\Market\Error;

class XmlNode extends Base
{
    use XmlNodeCompatible;

    /**
     * @deprecated
     * @noinspection PhpUnused
     */
    const PLAIN_TAG_NAME = Xml\Data\SimpleXmlCompiler::PLAIN_TAG_NAME;

	/** @var Xml\Data\ExportElement */
	protected $exportElement;

    /** @noinspection PhpUnused */
	public function registerError($errorMessage, $errorCode = null)
	{
        $this->addError($this->createError($errorMessage, $errorCode));
	}

    /** @noinspection PhpUnused */
    public function registerWarning($errorMessage, $errorCode = null)
	{
		$this->addWarning($this->createError($errorMessage, $errorCode));
	}

	protected function createError($errorMessage, $errorCode = null)
	{
		return new Error\XmlNode($errorMessage, $errorCode);
	}

	public function setExportElement(Xml\Data\ExportElement $exportElement)
	{
		$this->exportElement = $exportElement;
	}

	public function getExportElement()
	{
		return $this->exportElement;
	}
}