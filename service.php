<?php

use Goutte\Client;

class DoctorService extends ApretasteService
{
	/**
	 * Function executed when the service is called
	 */
	public function _main()
	{
		$this->response->setCache("year");
		$this->response->setTemplate("home.ejs");
	}

	/**
	 * Get a medical article
	 *
	 */
	public function _articulo()
	{
		// lower case and remove tildes for the term
		$term = trim(strtolower($this->request->input->data->query));

		// get the ID for that article
		$res = $this->getArticleId($term);

		// respond with error if article not found
		if (empty($res['artid'])) {
			return $this->response->setTemplate("message.ejs", ["term"=>$term, "similars"=>$res['similars']]);
		}

		// get the article for the ID
		$article = $this->getArticle($res['artid']);

		// create array to send info to the view
		$content = [
			"term"     => $article['title'],
			"result"   => $article['body'],
			"similars" => $res['similars']
		];

		// create the response
		$this->response->setCache("year");
		$this->response->setTemplate("basic.ejs", $content);
	}

	/**
	 * Get a medical article having the article ID
	 */
	public function _similar()
	{
		// get the query
		$result = $this->getArticle($this->request->input->data->query);

		// treat empty searches
		if (empty($result)) {
			$this->simpleMessage("No hay respuesta", "No encontramos una respuesta a su búsqueda. Por favor trate con otro término.");
			return;
		}

		// create an object to send to the template
		$content = [
			"term" => $result['title'],
			"result" => $result['body'],
			"similars" => []
		];

		// create the response
		$this->response->setCache("year");
		$this->response->setTemplate("basic.ejs", $content);
	}

	/**
	 * Get an article based on the ID
	 */
	private function getArticleId($term) 
	{
		// lower case and remove tildes for the term
		$term = trim(strtolower($term));
		$article = "";
		$similar_terms = [];

		// load from cache if exists
		$cache = Utils::getTempDir() . date("Y") . "_doctor_article_" . md5($term) . ".tmp";
		if(file_exists($cache)) $content = unserialize(file_get_contents($cache));

		// get data from the internet
		else {
			// get the right URL to pull info
			$first = $term[0];
			if (strpos("ABCDEFGHIJKLMNOPQRSTUVWXYZ0987654321", strtoupper($first)) !== false) {
				if ($first == 'x' || $first == 'y' || $first == 'z') $first = 'xyz';
				if (strpos("0987654321", $first) !== false) $url = "https://medlineplus.gov/spanish/healthtopics_0-9.html";
				else $url = "https://medlineplus.gov/spanish/healthtopics_{$first}.html";
			}

			// create a crawler
			$client = new Client();
			$guzzle = $client->getClient();
			$crawler = $client->request('GET', $url);

			try {
				$result = $crawler->filter("a")->each(function ($node, $i) use ($term, &$article, &$similar_terms) {
					if (
						strpos($node->attr('href'), "https://medlineplus.gov/spanish/") !== false
						&& strpos($node->attr('href'), "https://medlineplus.gov/spanish/healthtopics_") === false
					){
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
			} catch (exception $e) { }

			// remove the current ID from the list of similar terms
			$artid = str_replace(['https://medlineplus.gov/spanish/', '.html', './', 'article'], '', $article);
			if (isset($similar_terms[$artid])) unset($similar_terms[$artid]);

			// return values
			$content =  ["term"=>$term, "artid"=>$artid, "similars"=>$similar_terms];

			// save cache file
			file_put_contents($cache, serialize($content));
		}

		return $content;
	}

	/**
	 * Get an article based on the ID
	 */
	private function getArticle($artid)
	{
		// load from cache if exists
		$cache = Utils::getTempDir() . date("Y") . "_doctor_artid" . md5($artid) . ".tmp";
		if(file_exists($cache)) $content = unserialize(file_get_contents($cache));

		// get data from the internet
		else {
			// get the crawler object
			try {
				$url = "https://medlineplus.gov/spanish/$artid.html";
				$client = new Client();
				$crawler = $client->request('GET', $url);
			} catch (exception $e) { return false; }

			// get the title
			$title = "";
			try {
				$title = $crawler->filter("div.page-title >h1")->text();
			} catch (exception $e) { }

			// get the summary
			$summary = "";
			try {
				$summary = $crawler->filter("div#topic-summary")->html();
				$summary = preg_replace('#<a.*?>(.*?)</a>#i', '\1', $summary); // remove links
			} catch (exception $e) { }

			// return values
			$content =  ['title' => $title, 'body' => $summary];

			// save cache file
			file_put_contents($cache, serialize($content));
		}

		return $content;
	}
}
