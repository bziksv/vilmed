<?php
namespace Yandex\Market\Export\Xml;

use Yandex\Market\Result;
use Yandex\Market\Error;
use Yandex\Market\Export\Xml;

final class TagExporter
{
    private $level = 0;
    private $context;

    public function __construct(array $context)
    {
        $this->context = $context;
    }

    public function export(Tag\Base $tag, array $groupValues, Xml\Data\ExportElement $parent)
    {
        return $this->exportSingle($tag, $groupValues, $parent);
    }
    
    /** @return Result\XmlNode */
    private function exportSingle(Tag\Base $tag, array $groupValues, Xml\Data\ExportElement $parent)
    {
        $tagValue = $this->singleTagValue($tag, $groupValues);

        return $this->compileTag($tag, $tagValue, $groupValues, $parent);
    }

    /** @return Result\XmlNode[] */
    private function exportMultiple(Tag\Base $tag, array $groupValues, Xml\Data\ExportElement $parent)
    {
        $result = [];
        $maxCount = $tag->getMaxCount();
        $tagCount = 0;
        $tagValues = $this->multipleTagValues($tag, $groupValues);

        if (empty($tagValues)) { $tagValues[] = []; } // try export defaults

        foreach ($tagValues as $tagValue)
        {
            $exportResult = $this->compileTag($tag, $tagValue, $groupValues, $parent);
            $result[] = $exportResult;

            if (!$exportResult->isSuccess()) { continue; }

            ++$tagCount;

            if ($maxCount !== null && $tagCount >= $maxCount) { break; }
        }

        return $result;
    }

    /** @return Result\XmlNode[] */
    private function exportUnion(Tag\Base $tag, array $groupValues, Xml\Data\ExportElement $parent)
    {
        $result = [];
        $maxCount = $tag->getMaxCount();
        $tagCount = 0;
        $tagValues = $this->multipleTagValues($tag, $groupValues);
        $unionTag = null;

        if (empty($tagValues)) { $tagValues[] = []; } // try export defaults

        foreach ($tagValues as $tagValue)
        {
            $exportResult = $unionTag !== null
                ? $this->compileUnion($tag, $tagValue, $groupValues, $unionTag)
                : $this->compileTag($tag, $tagValue, $groupValues, $parent);

            $result[] = $exportResult;

            if (!$exportResult->isSuccess()) { continue; }

            ++$tagCount;

            if ($unionTag === null) { $unionTag = $exportResult->getExportElement(); }

            if ($maxCount !== null && $tagCount >= $maxCount) { break; }
        }

        return $result;
    }

    /** @return Result\XmlNode */
    private function compileTag(Tag\Base $tag, $tagValue, array $siblingsValues, Xml\Data\ExportElement $parent)
    {
        $result = new Result\XmlNode();
        $value = null;
        $hasEmptyValue = $tag->hasEmptyValue();

		if ($tag instanceof Tag\Concerns\HasTagValueChecker && !$this->checkTagValue($tag, $tagValue, $result))
		{
			return $result;
		}

        if (!$hasEmptyValue)
        {
            $value = isset($tagValue['VALUE']) && $tagValue['VALUE'] !== '' ? $tagValue['VALUE'] : $tag->getDefaultValue($this->context, $siblingsValues);
            $value = $tag->sanitize($value, $this->context, $tagValue, $siblingsValues);

            if (!$this->checkSanitized($tag, $value, $result)) { return $result; }
        }

        $node = $tag->insertNode($value, $parent);

        $hasAttributes = $this->compileAttributes($tag, $tagValue, $siblingsValues, $node, $result);
        $hasChildren = $this->compileChildren($tag, $tagValue, $siblingsValues, $node, $result);

        if ($hasEmptyValue && !$hasChildren && !$hasAttributes)
        {
            $this->markEmptyResult($tag, $result);
        }

        if ($tag instanceof Tag\Concerns\HasCompiledChecker && $result->isSuccess())
        {
            $error = $tag->checkCompiled($node, $parent);

            if ($error instanceof Error\Base)
            {
                $result->addError($this->extendError($error, $tag));
            }
        }

        if (($hasChildren || $hasAttributes) && $tag->getParameter('critical') === true)
        {
            foreach ($result->getErrors() as $error)
            {
                $error->markCritical();
            }
        }

        if (!$result->isSuccess())
        {
            $tag->removeNode($node, $parent);
            return $result;
        }

        $result->setExportElement($node);

        return $result;
    }

    /** @return bool */
    private function compileAttributes(Tag\Base $tag, $tagValue, array $siblingsValues, Xml\Data\ExportElement $parent, Result\XmlNode $tagResult)
    {
        $result = false;
        $values = isset($tagValue['ATTRIBUTES']) ? $tagValue['ATTRIBUTES'] : [];

        foreach ($tag->getAttributes() as $attribute)
        {
            $id = $attribute->getId();
            $value = isset($values[$id]) && $values[$id] !== '' ? $values[$id] : $attribute->getDefaultValue($this->context, $values);
            $value = $attribute->sanitize($value, $this->context, $tagValue, $siblingsValues);

            if (!$this->checkSanitized($tag, $value, $tagResult, $attribute)) { continue; }

            $result = true;
            $attribute->insertNode($value, $parent);
        }

        return $result;
    }

    /** @return bool */
    private function compileChildren(Tag\Base $tag, $tagValue, array $siblingsValues, Xml\Data\ExportElement $parent, Result\XmlNode $tagResult)
    {
        $result = false;

        if ($this->level === 0 || $tag->getParameter('tree') === false)
        {
            $childrenValues = $siblingsValues;
        }
        else
        {
            $childrenValues = isset($tagValue['CHILDREN']) ? (array)$tagValue['CHILDREN'] : [];
        }

        ++$this->level;

        foreach ($tag->getChildren() as $child)
        {
            $isError = $child->isRequired(); // error for parent if children required

            if ($child->isUnion())
            {
                $childrenResult = $this->exportUnion($child, $childrenValues, $parent);
            }
            else if ($child->isMultiple())
            {
                $childrenResult = $this->exportMultiple($child, $childrenValues, $parent);
            }
            else
            {
                $childResult = $this->exportSingle($child, $childrenValues, $parent);
                $childrenResult = [ $childResult ];
            }

            foreach ($childrenResult as $childResult)
            {
                if ($childResult->isSuccess())
                {
                    $result = true;
                    $isError = false;
                    break;
                }
            }

            $this->copyResultList($childrenResult, $tagResult, $isError);
        }

        --$this->level;

        return $result;
    }

    private function compileUnion(Tag\Base $tag, $tagValue, array $siblingsValues, Xml\Data\ExportElement $union)
    {
        $result = new Result\XmlNode();

        if ($tag->hasEmptyValue()) { return $result; }

        $value = isset($tagValue['VALUE']) && $tagValue['VALUE'] !== '' ? $tagValue['VALUE'] : $tag->getDefaultValue($this->context, $siblingsValues);
        $value = $tag->sanitize($value, $this->context, $tagValue, $siblingsValues);

        if (!$this->checkSanitized($tag, $value, $result)) { return $result; }

        $tag->appendNode($value, $union);

        return $result;
    }

	private function checkTagValue(Tag\Concerns\HasTagValueChecker $tag, $tagValue, Result\XmlNode $nodeResult)
	{
		$error = $tag->checkTagValue($tagValue);

		if ($error instanceof Error\SkipError)
		{
			$nodeResult->invalidate();
			return false;
		}

		if ($error instanceof Error\Base)
		{
			$nodeResult->addError($error);
			return false;
		}

		return true;
	}

    private function checkSanitized(Tag\Base $tag, $sanitized, Result\XmlNode $nodeResult, Attribute\Base $attribute = null)
    {
        if ($sanitized === null || $sanitized === '')
        {
            $this->markEmptyResult($tag, $nodeResult, $attribute);
            return false;
        }

        if ($sanitized instanceof Error\SkipError)
        {
            if ($attribute === null)
            {
                $nodeResult->invalidate();
            }

            return false;
        }

        if ($sanitized instanceof Error\Base)
        {
            $sanitized = $this->extendError($sanitized, $tag, $attribute);

            if ($attribute !== null && !$attribute->isRequired())
            {
                $nodeResult->addWarning($sanitized);
                return false;
            }

            $nodeResult->addError($sanitized);
            return false;
        }

        return true;
    }

    private function markEmptyResult(Tag\Base $tag, Result\XmlNode $nodeResult, Attribute\Base $attribute = null)
    {
        if ($attribute !== null)
        {
            if (!$attribute->isRequired()) { return; }

            $error = Error\XmlNode::createValidateEmpty();
            $error = $this->extendError($error, $tag, $attribute);

            $nodeResult->addError($error);

            return;
        }

        if ($tag->isRequired())
        {
            $error = Error\XmlNode::createValidateEmpty();
            $error = $this->extendError($error, $tag);

            $nodeResult->addError($error);
            return;
        }

        $nodeResult->invalidate();
    }
    
    private function extendError(Error\Base $error, Tag\Base $tag, Attribute\Base $attribute = null)
    {
        if ($error instanceof Error\XmlNode)
        {
            $error->setTagName($tag->getTitle());

            if ($attribute !== null)
            {
                $error->setAttributeName($attribute->getTitle());
            }
        }

        return $error;
    }

    private function copyResultList(array $fromList, Result\XmlNode $to, $isError)
    {
        $foundErrorMessages = [];
        $foundWarningMessages = [];

        /** @var Result\XmlNode $from */
        foreach ($fromList as $from)
        {
            foreach ($from->getErrors() as $error)
            {
                if ($isError || $error->isCritical())
                {
                    $errorUniqueKey = $error->getUniqueKey();

                    if (isset($foundErrorMessages[$errorUniqueKey])) { continue; }

                    $foundErrorMessages[$errorUniqueKey] = true;

                    $to->addError($error);
                }
                else if ($error->getCode() !== Error\XmlNode::XML_NODE_VALIDATE_EMPTY)
                {
                    $errorUniqueKey = $error->getUniqueKey();

                    if (isset($foundErrorMessages[$errorUniqueKey])) { continue; }

                    $foundWarningMessages[$errorUniqueKey] = true;

                    $to->addWarning($error);
                }
            }

            foreach ($from->getWarnings() as $warning)
            {
                $warningUniqueKey = $warning->getUniqueKey();

                if (!isset($foundWarningMessages[$warningUniqueKey]))
                {
                    $foundWarningMessages[$warningUniqueKey] = true;

                    $to->addWarning($warning);
                }
            }
        }

        if ($isError && empty($foundErrorMessages))
        {
            $to->invalidate();
        }
    }

    private function singleTagValue(Tag\Base $tag, array $groupValues)
    {
        $id = $tag->getId();

        if (!isset($groupValues[$id]))
        {
            if ($tag instanceof Tag\Concerns\HasTagValueModifier)
            {
                list($tagValue) = $tag->modifyTagValues([], $this->context);

                return $tagValue;
            }

            return null;
        }

        $tagValue = $groupValues[$id];

        if (!isset($tagValue['VALUE']) && !array_key_exists('VALUE', $tagValue))
        {
            $tagValue = reset($tagValue);
        }

        if ($tag instanceof Tag\Concerns\HasTagValueModifier)
        {
            list($tagValue) = $tag->modifyTagValues([ $tagValue ], $this->context);
        }

        return $tagValue;
    }

    private function multipleTagValues(Tag\Base $tag, array $groupValues)
    {
        $id = $tag->getId();

        if (!isset($groupValues[$id]))
        {
            if ($tag instanceof Tag\Concerns\HasTagValueModifier)
            {
                return $tag->modifyTagValues([], $this->context);
            }

            return [];
        }

        $tagValues = $groupValues[$id];

        if (isset($tagValues['VALUE']) || array_key_exists('VALUE', $tagValues))
        {
            $tagValues = [ $tagValues ];
        }

        if ($tag instanceof Tag\Concerns\HasTagValueModifier)
        {
            $tagValues = $tag->modifyTagValues($tagValues, $this->context);
        }

        return $tagValues;
    }
}