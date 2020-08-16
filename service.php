<?php

use Framework\Crawler;
use Apretaste\Request;
use Apretaste\Response;

class Service
{

	/**
	 * Function executed when the service is called
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _main(Request $request, Response &$response)
	{
		$images = [SERVICE_PATH . 'doctor' . "/images/main_logo.png"];

		$response->setCache('year');
		$response->setTemplate('home.ejs', ['term' => '', 'result' => ''], $images);
	}

	/**
	 * Get a medical article
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _articulo(Request $request, Response $response)
	{
		// lower case and remove tildes for the term
		$term = strtolower(trim($request->input->data->query ?? ''));

		$res = null;

		if (!empty($term)) {
			// get the ID for that article
			$res = $this->getArticleId($term);
		}

		// respond with error if article not found
		if (empty($res['artid'])) {
			$response->setTemplate('message.ejs', [
				'term' => $term,
				'similars' => $res['similars'] ?? []
			]);
			return;
		}

		// get the article for the ID
		$article = $this->getArticle($res['artid']);

		// create array to send info to the view
		$content = [
			'term' => $article['title'],
			'result' => $article['body'],
			'similars' => $res['similars']
		];

		// create the response
		$response->setCache('year');
		$response->setTemplate('basic.ejs', $content);
	}

	/**
	 * Get a medical article having the article ID
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _similar(Request $request, Response &$response)
	{
		// get the query
		$result = $this->getArticle($request->input->data->query);

		// treat empty searches
		if (empty($result)) {
			$response->setTemplate('message.ejs', [
				'term' => $request->input->data->query,
				'similars' => []
			]);
			return;
		}

		// create an object to send to the template
		$content = [
			'term' => $result['title'],
			'result' => $result['body'],
			'similars' => []
		];

		// create the response
		$response->setCache('year');
		$response->setTemplate('basic.ejs', $content);
	}

	/**
	 * Get an article based on the ID
	 */
	private function getArticleId($term)
	{
		// lower case and remove tildes for the term
		$term = trim(strtolower($term));
		$article = '';
		$similar_terms = [];

		// load from cache if exists
		$cacheName = 'article_' . md5($term);
		$content = self::loadCache($cacheName);

		if ($content === null) {
			// get the right URL to pull info
			$first = $term[0];
			if (stripos('ABCDEFGHIJKLMNOPQRSTUVWXYZ0987654321', $first) !== false) {
				if ($first === 'x' || $first === 'y' || $first === 'z') {
					$first = 'xyz';
				}

				if (strpos('0987654321', $first) !== false) {
					$url = 'https://medlineplus.gov/spanish/healthtopics_0-9.html';
				} else {
					$url = "https://medlineplus.gov/spanish/healthtopics_{$first}.html";
				}

				// create a crawler
				Crawler::start($url);

				try {
					$result = Crawler::filter('a')->each(function ($node, $i) use ($term, &$article, &$similar_terms) {
						if (
							strpos($node->attr('href'), 'https://medlineplus.gov/spanish/') !== false
							&& strpos($node->attr('href'), 'https://medlineplus.gov/spanish/healthtopics_') === false
						) {
							// lower text
							$text = strtolower($node->text());

							// add all similar terms to the list of similar
							similar_text($text, $term, $simil);
							if ($simil >= 60) {
								$art_id = $node->attr('href');
								$art_id = str_replace(['https://medlineplus.gov/spanish/', '.html'], '', $art_id);
								$similar_terms[$art_id] = $node->text();
							}

							// find the term that is closer to the text passed
							$max = 0;
							if ($simil > $max && $simil >= 85) {
								$max = $simil;
								$article = $node->attr('href');
							}
						}
					});
				} catch (exception $e) {
				}

				// remove the current ID from the list of similar terms
				$artid = str_replace(['https://medlineplus.gov/spanish/', '.html', './', 'article'], '', $article);
				if (isset($similar_terms[$artid])) {
					unset($similar_terms[$artid]);
				}

				// return values
				$content = ['term' => $term, 'artid' => $artid, 'similars' => $similar_terms];

				// save cache file
				self::saveCache($content, $cacheName);
			}
		}

		return $content;
	}

	/**
	 * Get an article based on the ID
	 *
	 * @param $artid
	 *
	 * @return array|bool|mixed
	 */
	private function getArticle($artid)
	{
		// load from cache if exists
		$cacheName = "art_id_$artid";
		$content = self::loadCache($cacheName);

		if ($content === null) {

			// get the crawler object
			$url = "https://medlineplus.gov/spanish/$artid.html";

			try {
				Crawler::start($url);
			} catch (exception $e) {
				return false;
			}

			// get the title
			try {
				$title = Crawler::filter('div.page-title >h1')->text();

				// get the summary
				$summary = '';
				try {
					$summary = Crawler::filter('div#topic-summary')->html();
					$summary = preg_replace('#<a.*?>(.*?)</a>#i', '\1', $summary); // remove links
				} catch (exception $e) {
				}

				// return values
				$content = ['title' => $title, 'body' => $summary];

				// save cache file
				self::saveCache($content, $cacheName);
			} catch (exception $e) {
			}
		}

		return $content;
	}


	/**
	 * Get cache file name
	 *
	 * @param $name
	 *
	 * @return string
	 */
	public static function getCacheFileName($name): string
	{
		return TEMP_PATH . 'cache/doctor_' . $name . '_' . date('Ymd') . '.tmp';
	}

	/**
	 * Load cache
	 *
	 * @param $name
	 * @param null $cacheFile
	 *
	 * @return bool|mixed
	 */
	public static function loadCache($name = 'cache', &$cacheFile = null)
	{
		$data = null;
		$cacheFile = self::getCacheFileName($name);
		if (file_exists($cacheFile)) {
			$data = unserialize(file_get_contents($cacheFile));
		}
		return $data;
	}

	/**
	 * Save cache
	 *
	 * @param $name
	 * @param $data
	 * @param null $cacheFile
	 */
	public static function saveCache($data, $name = 'cache', &$cacheFile = null)
	{
		$cacheFile = self::getCacheFileName($name);
		file_put_contents($cacheFile, serialize($data));
	}
}
