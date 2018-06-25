<?php

namespace Stackoverflow;

use Exception;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com>
 * @license MIT
 * @package Stackoverflow
 * @version 0.0.1
 */
final class Stackoverflow
{
	/**
	 * @var string
	 */
	private $query;

	/**
	 * @var string
	 */
	private $hash;

	/**
	 * @var string
	 */
	private $storagePath;

	/**
	 * @var array
	 */
	private $cachedData;

	/**
	 * @var int
	 */
	private $page;

	/**
	 * Constructor.
	 * 
	 * @param string $query
	 * @return void
	 */
	public function __construct($query)
	{
		$this->query = trim(strtolower($query));
		$this->hash  = sha1($this->query);

		if (! defined("data")) {
			$this->storagePath = "/tmp/stackoverflow";
		} else {
			is_dir(data) or mkdir(data);
			$this->storagePath = data."/stackoverflow";
		}

		$this->cachePath = $this->storagePath."/cache";

		is_dir($this->storagePath) or mkdir($this->storagePath);
		is_dir($this->cachePath) or mkdir($this->cachePath);

		if (! is_dir($this->storagePath)) {
			throw new StackoverflowException(
				"Cannot create storage directory ".$this->storagePath
			);
		}

		if (! is_dir($this->cachePath)) {
			throw new StackoverflowException(
				"Cannot create cache storage directory ".$this->cachePath
			);
		}
	}

	/**
	 * @param int $page
	 * @return void
	 */
	public function setPage(int $page)
	{
		$this->page = $page;
	}

	/**
	 * @return bool
	 */
	private function isCached()
	{
		if (file_exists($f = $this->cachePath."/".$this->hash)) {
			$f = $this->cachedData = json_decode(file_get_contents($f), true);
			if (is_array($f)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array
	 */
	private function onlineSearch()
	{
		$ch = curl_init(
			"https://stackoverflow.com/search?page=".($this->page)."&tab=Relevance&q=".urlencode($this->query)
		);
		if (! defined("CURL_HTTP_VERSION_2_0")) {
    		define("CURL_HTTP_VERSION_2_0", 3);
		}
		curl_setopt_array($ch, 
			[
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_HTTPHEADER => [
					"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
					"Accept-Language: en-US,en;q=0.5",
					"Accept-Encoding: gzip, deflate, br",
					"Connection: keep-alive",
					"Upgrade-Insecure-Requests: 1",
					"Cache-Control: max-age=0"
				],
				CURLOPT_USERAGENT => "Mozilla/5.0 (X11; Linux x86_64; rv:58.0) Gecko/20100101 Firefox/58.0",
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0
			]
		);
		$out = gzdecode(curl_exec($ch));
		$info = curl_getinfo($ch);
		if ($ern = curl_errno($ch)) {
			$err = curl_error($ch);
			curl_close($ch);
			throw new StackoverflowException(
				"Error (".$ern."): ".$err
			);
		}
		curl_close($ch);

		// file_put_contents("a.tmp", $out);
		// $out = file_get_contents("a.tmp");

		if ($r = $this->parseRawData($out)) {
			file_put_contents($this->cachePath."/".$this->hash, json_encode($r, JSON_UNESCAPED_SLASHES));
		}
		return $r;
	}

	/**
	 * @param string $str
	 * @return array
	 */
	private function parseRawData($str)
	{

		if (preg_match_all(
			"/<div class=\"result-link\">.+<h3>.+<a href=\"([^\s]*)\".+title=\"(.*)\" class.+>.+<div class=\"excerpt\">(.+)<\/div>/Usi", 
			$str, 
			$m
		)) {

			$res = [];
			foreach ($m[1] as $k => $v) {
				$res[] = [
					"link" => $v,
					"title" => $m[2][$k],
					"desc" => str_replace(
						["\n\n", "\n\n", "<span class=\"result-highlight\">", "</span>", "  "], 
						["\n", "\n", "<b>", "</b>", " "], 
						trim($m[3][$k])
					)
				];
			}
			return $res;
		} else {
			return [];
		}
	}

	/**
	 * @return array
	 */
	public function exec()
	{
		if ($this->isCached()) {
			return $this->cachedData;
		}
		return $this->onlineSearch();
	}
}

class StackoverflowException extends Exception
{	
}

