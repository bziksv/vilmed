<?php
namespace Yandex\Market\Export\Xml\Listing;

class Custom implements Listing
{
    private $values;
    private $synonyms;

    public function __construct(array $values, array $synonyms = [])
    {
        $this->values = $values;
        $this->synonyms = $synonyms;
    }

    public function values()
    {
        return $this->values;
    }

    public function display($value)
    {
        return $value;
    }

    public function synonyms($value)
    {
		if (empty($this->synonyms)) { return []; }

        $index = array_search($value, $this->values, true);

        if ($index === false || !isset($this->synonyms[$index])) { return []; }

        $synonym = $this->synonyms[$index];

        return is_array($synonym) ? $synonym : [ $synonym ];
    }
}