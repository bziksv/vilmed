<?php
namespace Yandex\Market\Export\Xml\Data;

interface ExportElement
{
	/** @return string */
	public function getName();

	/** @return mixed|null */
	public function getValue();

    public function setValue($value);

    public function appendValue($value, $glue);

    /** @return bool */
	public function hasChildren();

	/** @return static[] */
	public function getChildren();

	/** @return static[] */
	public function getChild($name);

	/** @return static */
	public function addChild($name, $value = null, $multiple = false);

	public function removeChild(ExportElement $child);

	public function build();
}