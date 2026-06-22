<?php
namespace Bitrix\EsolImportxml;

class HttpClient extends \Bitrix\Main\Web\HttpClient
{
	protected static $mProxyList = null;
	protected static $useProxy = true;
	var $lastError = '';
	var $lastErrorHost = '';
	
	public function __construct(array $options = null)
	{
		if($options['socketTimeout']) $options['socketTimeout'] = min($options['socketTimeout'], ($options['socketTimeout'] > 1800 ? 60 : 15));
		if($options['useProxy']===false) self::$useProxy = false;
		parent::__construct($options);
	}
	
	public function mInitProxyParams()
	{
		if(!isset(self::$mProxyList))
		{
			$moduleId = Utils::GetModuleId();
			$arProxies = \Bitrix\EsolImportxml\Utils::Unserialize(\Bitrix\Main\Config\Option::get($moduleId, 'PROXIES'));
			if(!is_array($arProxies)) $arProxies = array();
			if(count($arProxies)==0)
			{
				$arProxies[] = array(
					'HOST' => \Bitrix\Main\Config\Option::get($moduleId, 'PROXY_HOST', ''), 
					'PORT' => \Bitrix\Main\Config\Option::get($moduleId, 'PROXY_PORT', ''), 
					'USER' => \Bitrix\Main\Config\Option::get($moduleId, 'PROXY_USER', ''), 
					'PASSWORD' => \Bitrix\Main\Config\Option::get($moduleId, 'PROXY_PASSWORD', '')
				);
			}
			foreach($arProxies as $k=>$v)
			{
				if(!$v['HOST'] || !$v['PORT']) unset($arProxies[$k]);
			}
			self::$mProxyList = array_values($arProxies);
		}

		while(count(self::$mProxyList) > 0)
		{
			$key = rand(0, count(self::$mProxyList) - 1);
			$p = self::$mProxyList[$key];
			if(!array_key_exists('CHECKED', $p))
			{
				if($fp = fsockopen($p['HOST'], $p['PORT'], $errno, $errstr, 3))
				{
					self::$mProxyList[$key]['CHECKED'] = true;
					fclose($fp);
				}
				else
				{
					unset(self::$mProxyList[$key]);
					self::$mProxyList = array_values(self::$mProxyList);
					continue;
				}
			}
			$this->setProxy($p['HOST'], $p['PORT'], $p['USER'], $p['PASSWORD']);
			return $p;
		}
		return false;
	}
	
	public function cdownload($url, $filePath)
	{
		$this->lastError = $this->lastErrorHost = '';
		if(preg_match('/^(https?:\/\/)([^:]*):(.*)@(.*\/.*)$/is', $url, $m))
		{
			$this->setHeader('Authorization', 'Basic '.base64_encode($m[2].':'.$m[3]));
			$url = $m[1].$m[4];
		}
		elseif(preg_match('/^(https?:\/\/)([^:]*)@(.*\/.*)$/is', $url, $m))
		{
			$this->setHeader('Authorization', 'Basic '.base64_encode($m[2].':'));
			$url = $m[1].$m[3];
		}
		
		if(self::$useProxy && ($p = $this->mInitProxyParams()) && preg_match('/^\s*https:/i', $url) && ($res = $this->mDownloadCurl($url, $filePath, $p))) return $res;	
		
		//$res = parent::download($url, $filePath);
		$res = $this->mDownloadCurl($url, $filePath);
		if(true /*in_array($this->getStatus(), array(426, 505, 0))*/) //maybe status 200 and size 0
		{
			//$filePath2 = \Bitrix\Main\IO\Path::convertPhysicalToLogical($filePath);
			$filePath2 = \Bitrix\Main\IO\Path::convertLogicalToPhysical($filePath);
			if(!file_exists($filePath2) || filesize($filePath2)==0 || in_array($this->getStatus(), array(426, 505)))
			{
				if(file_exists($filePath2)) unlink($filePath2);
				$res = parent::download($url, $filePath);
				//$res = $this->mDownloadCurl($url, $filePath);
				$this->status = $this->getStatus();
				if(!function_exists('curl_init') && ($newRes = $this->checkBlockAnswer($url, $filePath2))) $res = $newRes;
			}
		}
		return $res;
	}
	
	public function mDownloadCurl($url, $filePath, $p=array())
	{
		if(function_exists('curl_init'))
		{
			$arOrigHeaders = array();
			if(is_callable(array($this, 'getRequestHeaders'))) $arOrigHeaders = $this->getRequestHeaders()->toArray();
			elseif(isset($this->requestHeaders)) $arOrigHeaders = $this->requestHeaders->toArray();
			$arHeaders = array();
			$arSHeaders = array();
			foreach($arOrigHeaders as $header)
			{
				foreach($header["values"] as $value)
				{
					$arHeaders[] = $header["name"] . ": ".$value;
					$arSHeaders[$header["name"]] =  $value;
				}
			}
			if(array_key_exists('GZIP', $p) && $p['GZIP'])
			{
				$arHeaders[] = 'Accept-Encoding: gzip';
				$arSHeaders['Accept-Encoding'] = 'gzip';
			}
			$cookies = '';
			if(class_exists('\Bitrix\Main\Web\Http\Response'))
			{
				$this->response = new \Bitrix\Main\Web\Http\Response(0);
			}
			if(is_callable(array($this, 'getRequestHeaders'))) $cookies = $this->getRequestHeaders()->get('Cookie');
			elseif(isset($this->requestCookies)) $cookies = $this->requestCookies->toString();
			
			CheckDirPath($filePath);
			//$filePath2 = \Bitrix\Main\IO\Path::convertPhysicalToLogical($filePath);
			$filePath2 = \Bitrix\Main\IO\Path::convertLogicalToPhysical($filePath);
			$f = fopen($filePath2, 'w');
			$ch = curl_init();
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_URL,$url);
			if(isset($p['HOST']) && $p['HOST']) curl_setopt($ch, CURLOPT_PROXY, $p['HOST'].':'.$p['PORT']);
			if(isset($p['USER']) && $p['USER']) curl_setopt($ch, CURLOPT_PROXYUSERPWD, $p['USER'].':'.$p['PASSWORD']);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->redirect);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $arHeaders);
			if($arSHeaders['User-Agent']) curl_setopt($ch, CURLOPT_USERAGENT, $arSHeaders['User-Agent']);
			if(strlen($cookies) > 0) curl_setopt($ch, CURLOPT_COOKIE, $cookies);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->socketTimeout);
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->streamTimeout);
			curl_setopt($ch, CURLOPT_FILE, $f);
			curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'mCurlGetHeaders'));
			$res = curl_exec($ch);
			curl_close($ch);
			fclose($f);
			
			if(array_key_exists('GZIP', $p) && $p['GZIP'])
			{
				if(ToLower($this->getHeaders()->get('Content-encoding'))=='gzip' && file_exists($filePath2))
				{
					file_put_contents($filePath2, gzdecode(file_get_contents($filePath2)));
				}
			}
			elseif(curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) > 0 && curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD) > 0 && curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) > curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD))
			{
				$p['GZIP'] = true;
				if(file_exists($filePath2)) unlink($filePath2);
				return $this->mDownloadCurl($url, $filePath, $p);
			}

			$this->status = $this->mCurlStatus = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
			if($this->response && is_callable(array($this->response, 'getHeadersCollection')) && is_callable(array($this->response->getHeadersCollection(), 'setStatus')))
			{
				$this->response->getHeadersCollection()->setStatus($this->status);
			}
			
			if($this->status=='404')
			{
				$arUrl = parse_url($url);
				$host = $arUrl['host'];
				try
				{
					$ip = gethostbyname($host);
					if($ip && curl_getinfo($ch, CURLINFO_PRIMARY_IP) && $ip!=curl_getinfo($ch, CURLINFO_PRIMARY_IP))
					{
						$this->setHeader('Host', $host);
						return $this->mDownloadCurl(str_replace('//'.$host.'/', '//'.$ip.'/', $url), $filePath, $p);
					}
				}
				catch(\Exception $ex){}
			}
			
			if($newRes = $this->checkBlockAnswer($url, $filePath2)) $res = $newRes;

			return $res;			
		}
		return false;
	}
	
	public function getLastError()
	{
		if(!$this->lastError)
		{
			if($this->getStatus()==404) return 'STATUS_404';
			elseif(preg_match('/^[45]\d{2}$/', $this->getStatus())) return 'STATUS_'.$this->getStatus();
		}
		return $this->lastError;
	}
	
	public function mCurlGetHeaders($ch, $header)
	{
		$len = mb_strlen($header);
		$header = explode(':', $header, 2);
		if(count($header) < 2) return $len;
		
		$headerName = trim($header[0]);
		$headerValue = trim($header[1]);
		if(ToLower($headerName)=='set-cookie')
		{
			if(isset($this->responseCookies)) $this->responseCookies->addFromString($headerValue);
			else $this->getHeaders()->add('set-cookie', array_map('trim', explode(';', $headerValue)));
		}
		
		if(strpos($headerName, "\0") === false && preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $headerName)
			&& strpos($headerValue, "\0") === false && preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/', $headerValue))
		{
			if(isset($this->responseHeaders)) $this->responseHeaders->add($headerName, $headerValue);
			else $this->getHeaders()->add($headerName, $headerValue);
		}
		return $len;
	}
	
    public function getStatus()
    {
        if(isset($this->mCurlStatus)) return $this->mCurlStatus;
        return parent::getStatus();
    }
	
	public function checkBlockAnswer($url, $filePath)
	{
		//file_put_contents(dirname(__FILE__).'/test.txt', $this->status."\r\n".file_get_contents($filePath));
		
		/*
		if($this->status==307 && $this->getHeaders()->get('location')==$url)
		{
			$c = file_get_contents($filePath);
			if(strpos($c, 'blank')!==false)
			{
				$this->getFileFromAddon($url, $filePath);
			}
		}
		*/

		$useAddon = false;
		if((in_array($this->status, array(200, 403)) && stripos($this->getHeaders()->get('content-type'), 'text/html')!==false)
			|| ($this->status==403 && stripos($this->getHeaders()->get('content-type'), 'application/xml')!==false))
		{
			$c = file_get_contents($filePath);
			if(stripos($c, 'get_cookie_spsc_encrypted_part')!==false
				|| stripos($c, 'bot request')!==false
				|| stripos($c, 'SignatureDoesNotMatch')!==false
				|| stripos($c, 'document.cookie')!==false
				|| ($this->status==403 && preg_match('/ip:\s*\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $c)))
			{
				$useAddon = true;
			}
		}
		elseif($this->status==0 || strlen($this->status)==0 /*not connected*/)
		{
			$useAddon = true;
		}

		if($useAddon) return $this->getFileFromAddon($url, $filePath);
		return false;
	}
	
	public function getFileFromAddon($url, $filePath)
	{
		if(stripos(trim($url), 'http')!==0) return false;
		$domain = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : \Bitrix\Main\Config\Option::get('main', 'server_name'));
		$hash = md5($domain).'#'.md5($url);
		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'socketTimeout'=>1, 'streamTimeout'=>22));
		if($res = $ob->post('http://downloads.esolutions.su/getfile.php', array('domain'=>$domain, 'url'=>$url, 'hash'=>$hash)))
		{
			CheckDirPath($filePath);
			file_put_contents($filePath, $res);
			
			$imgData = getimagesize($filePath);
			if(is_array($imgData) && isset($imgData) && stripos($imgData['mime'], 'image')!==false)
			{
				if(isset($this->responseHeaders)) $this->responseHeaders->set('content-type', $imgData['mime']);
				else $this->getHeaders()->set('content-type', $imgData['mime']);
			}
			
			return true;
		}
		return false;
	}
}
