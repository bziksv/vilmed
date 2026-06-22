<?php
namespace Yandex\Market\Type;

use Bitrix\Main;

class UrlType extends AbstractType
{
	protected $idnCache = [];

    public function type()
    {
        return Manager::TYPE_URL;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		$parsedUrl = $this->parseUrl($value);

		if ($parsedUrl !== null)
		{
			if ($parsedUrl['DOMAIN'] === '') // no domain
			{
				$parsedUrl['DOMAIN'] = (string)$context['DOMAIN_URL'];
			}
			else if (mb_strpos($parsedUrl['DOMAIN'], '//') === 0) // without protocol
			{
                $https = (!isset($context['HTTPS']) || $context['HTTPS']);

				$parsedUrl['DOMAIN'] =
					($https ? 'https:' : 'http:')
					. $parsedUrl['DOMAIN'];
			}

			if ($parsedUrl['PATH'] !== '' && mb_strpos($parsedUrl['PATH'], '/') !== 0) // no start slash for path
			{
				$parsedUrl['PATH'] = '/' . $parsedUrl['PATH'];
			}

			$result =
				$this->idnDomain($parsedUrl['DOMAIN'])
				. $this->encodeUrlPath($parsedUrl['PATH'])
				. $parsedUrl['QUERY'];
		}
		else
		{
			$result = $value;
		}

		return $result;
	}

	protected function idnDomain($domain)
	{
		if (isset($this->idnCache[$domain]))
		{
			$result = $this->idnCache[$domain];
		}
		else
		{
			$errorList = [];
			$idnDomain = \CBXPunycode::ToASCII($domain, $errorList);
			$result = ($idnDomain !== false ? $idnDomain : $domain);

			$this->idnCache[$domain] = $result;
		}

		return $result;
	}

	protected function encodeUrlPath($path)
	{
		$result = $path;

		if (preg_match('#[^A-Za-z0-9-_.~/?=&]#', $path)) // has invalid chars
		{
			$charset = $this->getCharset();
			$parts = preg_split("#(://|:\\d+/|/|\\?|=|&)#", $path, -1, PREG_SPLIT_DELIM_CAPTURE);
			$result = '';

			foreach ($parts as $partIndex => $part)
			{
				if ($partIndex % 2 === 0)
				{
					if (preg_match('/%[0-9A-F]{2}/', $part)) // has encoded chars
					{
						$part = rawurldecode($part);
					}

					if ($charset !== false)
					{
						$part = Main\Text\Encoding::convertEncoding($part, LANG_CHARSET, $charset);
					}

					$part = rawurlencode($part);
				}

				$result .= $part;
			}
		}

		return $result;
	}

	protected function parseUrl($url)
	{
		$result = null;

		if (preg_match('#^((?:[A-Za-z]+?:)?//[^/?\#]+)?([^?\#]*)(.*)?$#', $url, $matches))
		{
			$result = [
				'DOMAIN' => $matches[1],
				'PATH' => $matches[2],
				'QUERY' => $matches[3],
			];
		}

		return $result;
	}

    /** @noinspection PhpDeprecationInspection */
	protected function getCharset()
	{
		if (!Main\Application::isUtfMode())
		{
			return 'UTF-8';
		}

		return false;
	}
}