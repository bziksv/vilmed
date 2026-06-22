<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Yandex\Market\Trading;

class SyncBusinessOptions
{
    protected $provider;
    protected $setupId;
    protected $businessId;

    public function __construct(Trading\Service\Marketplace\Provider $provider, $setupId, $businessId)
    {
        $this->provider = $provider;
        $this->setupId = (int)$setupId;
        $this->businessId = (int)$businessId;
    }

    public function install()
    {
        $values = $this->values();
        $siblings = $this->siblings();

        $this->copy($siblings, $values);
    }

    protected function values()
    {
        $values = [];

        foreach ([ 'API_KEY' ] as $name)
        {
            $value = trim($this->provider->getOptions()->getValue($name));

            if ($value === '') { continue; }

            $values[$name] = $value;
        }

        return $values;
    }

    protected function siblings()
    {
        if ($this->businessId <= 0) { return []; }

        $query = Trading\Setup\Table::getList([
            'filter' => [
                '!=ID' => $this->setupId,
                '=BUSINESS.ID' => $this->businessId,
            ],
            'select' => [ 'ID' ],
        ]);

        return array_values(array_column($query->fetchAll(), 'ID', 'ID'));
    }

    protected function copy(array $siblings, array $values)
    {
        $rows = $this->makeRows($siblings, $values);

        if (empty($rows)) { return; }

        Trading\Settings\Table::addBatch($rows, true);
    }

    protected function makeRows(array $siblings, array $values)
    {
        $result = [];

        foreach ($siblings as $sibling)
        {
            foreach ($values as $name => $value)
            {
                $result[] = [
                    'SETUP_ID' => $sibling,
                    'NAME' => $name,
                    'VALUE' => $value,
                ];
            }
        }

        return $result;
    }
}