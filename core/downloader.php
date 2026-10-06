<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\core;

/**
 * Minimal HTTP client. Downloads are done in byte ranges so that the ACP
 * can show a real percentage and so that no single request runs into
 * the PHP time limit. Uses cURL when available, PHP streams otherwise.
 */
class downloader
{
	/** @var string */
	protected $user_agent;

	/** @var int */
	protected $timeout = 30;

	public function __construct($board_url = '')
	{
		$this->user_agent = 'phpBB-TimezoneClock/1.0 (+' . ($board_url ?: 'https://netshadows.de') . ')';
	}

	public function set_timeout($seconds)
	{
		$this->timeout = max(2, (int) $seconds);
	}

	public static function available()
	{
		return function_exists('curl_init') || (bool) ini_get('allow_url_fopen');
	}

	public static function method()
	{
		if (function_exists('curl_init'))
		{
			return 'curl';
		}

		return ini_get('allow_url_fopen') ? 'stream' : '';
	}

	/**
	 * Plain GET
	 *
	 * @return array [ok, status, body, headers[], error, time_ms]
	 */
	public function get($url)
	{
		return $this->request($url, []);
	}

	/**
	 * GET a byte range. 'total' is read from Content-Range; when the server
	 * ignores ranges (status 200) the whole body is returned and 'full' is true.
	 */
	public function get_range($url, $from, $to)
	{
		$res = $this->request($url, ['Range: bytes=' . (int) $from . '-' . (int) $to]);
		$res['total'] = 0;
		$res['full'] = false;

		if ($res['ok'])
		{
			if ($res['status'] == 206 && isset($res['headers']['content-range']) && preg_match('#/(\d+)$#', $res['headers']['content-range'], $m))
			{
				$res['total'] = (int) $m[1];
			}
			else if ($res['status'] == 200)
			{
				$res['full'] = true;
				$res['total'] = strlen($res['body']);
			}
		}
		else if ($res['status'] == 416)
		{
			// Range beyond the end: nothing left to download
			$res['ok'] = true;
			$res['body'] = '';
		}

		return $res;
	}

	/**
	 * Lightweight availability probe (first byte only)
	 */
	public function probe($url)
	{
		$res = $this->get_range($url, 0, 0);
		$res['body'] = '';

		return $res;
	}

	protected function request($url, array $headers)
	{
		$start = microtime(true);
		$headers[] = 'User-Agent: ' . $this->user_agent;
		$headers[] = 'Accept: */*';

		$out = ['ok' => false, 'status' => 0, 'body' => '', 'headers' => [], 'error' => '', 'time_ms' => 0];

		if (function_exists('curl_init'))
		{
			$response_headers = [];
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER	=> true,
				CURLOPT_FOLLOWLOCATION	=> true,
				CURLOPT_MAXREDIRS		=> 5,
				CURLOPT_CONNECTTIMEOUT	=> min(10, $this->timeout),
				CURLOPT_TIMEOUT			=> $this->timeout,
				CURLOPT_HTTPHEADER		=> $headers,
				CURLOPT_HEADERFUNCTION	=> function ($ch, $line) use (&$response_headers) {
					$parts = explode(':', $line, 2);
					if (count($parts) === 2)
					{
						$response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
					}
					else if (stripos($line, 'HTTP/') === 0)
					{
						// new response after a redirect
						$response_headers = [];
					}
					return strlen($line);
				},
			]);

			$body = curl_exec($ch);
			$out['status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($body === false)
			{
				$out['error'] = curl_error($ch);
			}
			else
			{
				$out['body'] = $body;
			}
			curl_close($ch);
			$out['headers'] = $response_headers;
		}
		else if (ini_get('allow_url_fopen'))
		{
			$context = stream_context_create([
				'http' => [
					'method'			=> 'GET',
					'header'			=> implode("\r\n", $headers),
					'timeout'			=> $this->timeout,
					'follow_location'	=> 1,
					'ignore_errors'		=> true,
				],
			]);

			$body = @file_get_contents($url, false, $context);
			$raw_headers = isset($http_response_header) ? $http_response_header : [];
			foreach ($raw_headers as $line)
			{
				if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m))
				{
					$out['status'] = (int) $m[1];
					$out['headers'] = [];
					continue;
				}
				$parts = explode(':', $line, 2);
				if (count($parts) === 2)
				{
					$out['headers'][strtolower(trim($parts[0]))] = trim($parts[1]);
				}
			}

			if ($body === false)
			{
				$error = error_get_last();
				$out['error'] = $error ? $error['message'] : 'TZC_ERR_STREAM';
			}
			else
			{
				$out['body'] = $body;
			}
		}
		else
		{
			$out['error'] = 'TZC_ERR_NO_TRANSPORT';
		}

		$out['ok'] = ($out['error'] === '' && $out['status'] >= 200 && $out['status'] < 300);
		$out['time_ms'] = (int) round((microtime(true) - $start) * 1000);

		if (!$out['ok'] && $out['error'] === '')
		{
			$out['error'] = 'HTTP ' . $out['status'];
		}

		return $out;
	}
}
