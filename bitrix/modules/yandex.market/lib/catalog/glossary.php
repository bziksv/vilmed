<?php
namespace Yandex\Market\Catalog;

use Yandex\Market\Glossary as GlobalGlossary;

class Glossary
{
	const SERVICE_SELF = GlobalGlossary::SERVICE_CATALOG;

    const ENTITY_SETUP = GlobalGlossary::ENTITY_SETUP;
    const ENTITY_OFFER = GlobalGlossary::ENTITY_OFFER;
    const ENTITY_CURRENCY =  GlobalGlossary::ENTITY_CURRENCY;
    const ENTITY_SKU =  'sku';

	const SEGMENT_PRICE = 'price';
	const SEGMENT_STOCKS = 'stocks';
	const SEGMENT_OFFER = 'offer';
	const SEGMENT_CARD = 'card';

	const ENDPOINT_PRICE = 'price';
	const ENDPOINT_STOCKS = 'stocks';
	const ENDPOINT_OFFER = 'offer';
	const ENDPOINT_CARD = 'card';
	const ENDPOINT_TERMS = 'terms';
	const ENDPOINT_ARCHIVE = 'archive';
}