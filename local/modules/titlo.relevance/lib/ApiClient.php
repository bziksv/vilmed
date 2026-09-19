<?php

namespace Titlo\Relevance;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

class ApiClient
{
	/** @var string */
	protected $baseUrl;

	/** @var string */
	protected $apiKey;

	public function __construct(?string $baseUrl = null, ?string $apiKey = null)
	{
		$this->baseUrl = $baseUrl !== null ? rtrim($baseUrl, '/') : Config::apiBaseUrl();
		$this->apiKey = $apiKey !== null ? $apiKey : Config::apiKey();
	}

	public function startAnalysis(string $url, string $phrase, array $extra = []): array
	{
		return $this->request('POST', '/relevance/analyses', array_merge([
			'url' => $url,
			'phrase' => $phrase,
		], $extra));
	}

	public function getAnalysis(string $analysisId): array
	{
		return $this->request('GET', '/relevance/analyses/' . rawurlencode($analysisId));
	}

	public function getHistory(int $historyId): array
	{
		return $this->request('GET', '/relevance/histories/' . $historyId);
	}

	/**
	 * Список прошлых проверок посадочной (динамика баллов).
	 *
	 * @return array{latest?:array,items:array,count:int}
	 */
	public function listHistories(?string $url, ?string $phrase = null, int $limit = 10, ?string $siteHost = null): array
	{
		$qs = http_build_query(array_filter([
			'url' => $url !== null && $url !== '' ? $url : null,
			'phrase' => $phrase !== null && $phrase !== '' ? $phrase : null,
			'limit' => $limit,
			'site_host' => $siteHost !== null && $siteHost !== '' ? $siteHost : null,
		], static function ($v) {
			return $v !== null && $v !== '';
		}));
		return $this->request('GET', '/relevance/histories?' . $qs, [], 12);
	}

	public function getMissingPhrases(int $historyId, string $filter = 'zero'): array
	{
		return $this->request(
			'GET',
			'/relevance/histories/' . $historyId . '/missing-phrases?filter=' . rawurlencode($filter),
			[],
			45
		);
	}

	/**
	 * TLP для генерации: missing + diff, сортировка TF-IDF ТОП.
	 */
	public function getTlp(int $historyId): array
	{
		return $this->request(
			'GET',
			'/relevance/histories/' . $historyId . '/missing-phrases?mode=tlp',
			[],
			60
		);
	}

	/**
	 * TF-IDF облака посадочной и конкурентов.
	 *
	 * @return array{history_id?:int,competitors?:array,landing?:array}
	 */
	public function getClouds(int $historyId, int $limit = 100): array
	{
		$qs = $limit > 0 ? ('?limit=' . (int) $limit) : '';
		return $this->request('GET', '/relevance/histories/' . $historyId . '/clouds' . $qs, [], 60);
	}

	public function startGenerate(string $type, string $url, array $keywords = [], array $extra = []): array
	{
		if (!isset($extra['prompt']) || trim((string) $extra['prompt']) === '') {
			$promptId = isset($extra['prompt_id']) ? (int) $extra['prompt_id'] : 0;
			unset($extra['prompt_id']);
			$prompt = Prompts::get($type, $promptId > 0 ? $promptId : null);
			if ($prompt !== '') {
				$extra['prompt'] = $prompt;
			}
			if ($promptId > 0) {
				Prompts::markUsed($promptId);
			} else {
				$aid = Prompts::getActiveId($type);
				if ($aid > 0) {
					Prompts::markUsed($aid);
				}
			}
		} elseif (isset($extra['prompt_id'])) {
			Prompts::markUsed((int) $extra['prompt_id']);
			unset($extra['prompt_id']);
		}
		return $this->request('POST', '/ai/generate', array_merge([
			'type' => $type,
			'url' => $url,
			'keywords' => $keywords,
			'source' => 'parse_html',
		], $extra));
	}

	/**
	 * Short name for relevance analyzer (via cabinet AI).
	 * $promptType: phrase | section_phrase
	 */
	public function startPhraseGenerate(string $name, string $url = '', int $promptId = 0, string $promptType = 'phrase'): array
	{
		$promptType = Prompts::phraseTypeForEntity(
			$promptType === Prompts::TYPE_SECTION_PHRASE || $promptType === 'S' ? 'S' : 'E',
			false
		);
		if ($promptId <= 0) {
			$promptId = Prompts::getActiveId($promptType);
		}
		$payload = [
			'type' => 'phrase',
			'name' => $name,
			'source' => 'ai_database',
			'prompt' => Prompts::get($promptType, $promptId > 0 ? $promptId : null),
		];
		if ($url !== '') {
			$payload['url'] = $url;
		}
		if ($promptId > 0) {
			Prompts::markUsed($promptId);
		}
		return $this->request('POST', '/ai/generate', $payload);
	}

	/**
	 * Пачка коротких фраз (до 30) — один запрос к API / модели.
	 *
	 * @param array<int, array{name:string,url?:string,external_id?:string|int}> $items
	 * @param string $promptType phrase_batch | section_phrase_batch | E | S
	 */
	public function startPhraseBatch(array $items, int $promptId = 0, string $promptType = 'phrase_batch'): array
	{
		$promptType = Prompts::phraseTypeForEntity(
			$promptType === Prompts::TYPE_SECTION_PHRASE_BATCH
				|| $promptType === Prompts::TYPE_SECTION_PHRASE
				|| $promptType === 'S'
				? 'S'
				: 'E',
			true
		);
		if ($promptId <= 0) {
			$promptId = Prompts::getActiveId($promptType);
		}
		$payloadItems = [];
		foreach ($items as $row) {
			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$item = ['name' => $name];
			$url = trim((string) ($row['url'] ?? ''));
			if ($url !== '') {
				$item['url'] = $url;
			}
			if (isset($row['external_id']) && (string) $row['external_id'] !== '') {
				$item['external_id'] = (string) $row['external_id'];
			}
			$payloadItems[] = $item;
		}
		if ($promptId > 0) {
			Prompts::markUsed($promptId);
		}
		return $this->request('POST', '/ai/generate', [
			'type' => 'phrase',
			'source' => 'ai_database',
			'items' => $payloadItems,
			'prompt' => Prompts::get($promptType, $promptId > 0 ? $promptId : null),
		]);
	}

	public function getGenerate(int $recordId): array
	{
		return $this->request('GET', '/ai/generate/' . $recordId);
	}

	public function createBatch(array $items): array
	{
		return $this->request('POST', '/relevance/batches', ['items' => $items]);
	}

	public function getBatch(string $batchId): array
	{
		return $this->request('GET', '/relevance/batches/' . rawurlencode($batchId));
	}

	/**
	 * @return array
	 * @throws \RuntimeException
	 */
	protected function request(string $method, string $path, array $body = [], int $timeoutSec = 30): array
	{
		if ($this->apiKey === '') {
			throw new \RuntimeException('Titlo API key is not configured');
		}

		// redirect=false: Bearer не уходит на Location hop (exfil / SSRF)
		$http = new HttpClient([
			'socketTimeout' => $timeoutSec,
			'streamTimeout' => $timeoutSec,
			'redirect' => false,
		]);
		$http->setHeader('Authorization', 'Bearer ' . $this->apiKey);
		$http->setHeader('Accept', 'application/json');
		$http->setHeader('Content-Type', 'application/json');

		// Только path относительно настроенного base — без произвольных host
		$path = '/' . ltrim((string) $path, '/');
		$url = rtrim($this->baseUrl, '/') . $path;
		$payload = $body !== [] ? Json::encode($body) : null;

		if (strtoupper($method) === 'GET') {
			$ok = $http->get($url);
		} else {
			$ok = $http->query($method, $url, $payload);
		}

		$status = (int) $http->getStatus();
		$raw = (string) $http->getResult();
		$data = [];
		if ($raw !== '') {
			try {
				$data = Json::decode($raw);
			} catch (\Throwable $e) {
				$data = ['_raw' => $raw];
			}
		}

		if (!$ok || $status >= 400) {
			$message = is_array($data)
				? (string) ($data['message'] ?? $data['error'] ?? ('HTTP ' . $status))
				: ('HTTP ' . $status);
			if ($status === 0 && $message === 'HTTP 0') {
				$message = 'Кабинет не ответил за ' . $timeoutSec . ' с';
			}
			throw new \RuntimeException($message, $status > 0 ? $status : 500);
		}

		return is_array($data) ? $data : [];
	}
}
