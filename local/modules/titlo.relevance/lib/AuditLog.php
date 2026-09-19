<?php

namespace Titlo\Relevance;

/**
 * Аудит массовых / деструктивных операций модуля.
 */
class AuditLog
{
	public static function write(string $action, array $context = []): void
	{
		global $USER;
		$uid = (is_object($USER) && method_exists($USER, 'GetID')) ? (int) $USER->GetID() : 0;
		$payload = [
			'ts' => date('c'),
			'user_id' => $uid,
			'action' => $action,
			'context' => $context,
		];
		$line = 'titlo.relevance audit: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (function_exists('AddMessage2Log')) {
			AddMessage2Log($line, 'titlo.relevance');
		}
	}
}
