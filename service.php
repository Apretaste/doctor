<?php

use Goutte\Client;

class Doctor extends Service
{
	static $term = null;
	static $article = null;
	static $max = 0;
	static $temp = array();
	static $similar_terms = array();

	/**
	 * Function executed when the service is called
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _main(Request $request)
	{
		// do not allow blank searches
		if(empty($request->query))
		{
			$response = new Response();
			$response->setCache();
			$response->setResponseSubject("Que desea preguntale al doctor?");
			$response->createFromTemplate("home.tpl", array());
			return $response;
		}

		// lower case and remove tildes for the term
		self::$term = trim(strtolower($request->query));
		self::$term = $this->utils->removeTildes(self::$term);

		// get the right URL to pull info
		$url = "https://www.nlm.nih.gov/medlineplus/spanish/ency/encyclopedia_A.htm";
		$first = ucfirst(self::$term[0]);
		if (strpos("ABCDEFGHIJKLMNOPQRSTUVWXYZ0987654321", $first) !== false) {
			if (strpos("0987654321", $first) !== false) {
				$url = "https://www.nlm.nih.gov/medlineplus/spanish/ency/encyclopedia_0-9.htm";
			} else
				$url = "https://www.nlm.nih.gov/medlineplus/spanish/ency/encyclopedia_{$first}.htm";
		}

		$client = new Client();
		$guzzle = $client->getClient();

		// create a crawler
		try {
			$crawler = $client->request('GET', $url);
		} catch (exception $e) {
			// Send an error notice to programmer
			$this->utils->createAlert("DOCTOR: Error al leer resultados: ".self::$term, "ERROR");

			// respond to user
			$response = new Response();
			$response->setResponseSubject("DOCTOR: Estamos presentando problemas");
			$response->createFromText("Estamos presentando problemas con el doctor. Por favor intente m&aacute;s tarde. Hemos avisado al personal tecnico para corregir este error.");
			return $response;
		}

		try {
			$result = $crawler->filter("a")->each(function ($node, $i) {
				if (strpos($node->attr('href'), "article/") !== false) {
					// remove tildes and special chars
					$text = $this->utils->removeTildes($node->text());

					// add all similar terms to the list of similar
					similar_text($text, self::$term, $simil);
					if ($simil >= 60) {
						$art_id = $node->attr('href');
						$art_id = str_replace(array('article/', '.htm'), '', $art_id);
						self::$similar_terms[$art_id] = $node->text();
					}

					// find the term that is closer to the text passed
					if ($simil > self::$max && $simil >= 85) {
						self::$max = $simil;
						self::$article = $node->attr('href');
					}
				}
			});
		} catch (exception $e) {}

		// remove the current ID from the list of similar terms
		$artid = str_replace(array('article/','.htm','./','article'), '', self::$article);
		if (isset(self::$similar_terms[$artid])) unset(self::$similar_terms[$artid]);

		// respond with error if article not found
		if(empty(self::$article)){
			$response = new Response();
			$response->setCache();
			$response->setResponseSubject("No se encontro respuesta para " . self::$term);
			$response->createFromTemplate("not_found.tpl", array("term" => self::$term, "similars" => self::$similar_terms));
			return $response;
		}

		// get the article ID of the term selected
		$article = $this->getArticle($artid);

		// create array to send info to the view
		$responseContent = array(
			"term" => $article['title'],
			"result" => $article['body'],
			"similars" => self::$similar_terms
		);

		// create the response
		$response = new Response();
		$response->setCache();
		$response->setResponseSubject("Respuesta a su busqueda: " . self::$term);
		$response->createFromTemplate("basic.tpl", $responseContent);
		return $response;
	}

	/**
	 * Subservice DOCTOR ARTICULO ######
	 *
	 * @param Request
	 * @return Response
	 */
	public function _articulo(Request $request)
	{
		$result = $this->getArticle($request->query);

		if (empty($result)) {
			$response = new Response();
			$response->setCache();
			$response->setResponseSubject("No se encontro respuesta a su busqueda: " . self::$term);
			$response->createFromText("No se encontr&oacute; respuesta a su b&uacute;squeda: " . self::$term);
			return $response;
		}

		// create an object to send to the template
		$responseContent = array(
			"term" => $result['title'],
			"result" => $result['body'],
			"similars" => array()
		);

		// create the response
		$response = new Response();
		$response->setCache();
		$response->setResponseSubject("Enciclopedia medica:" . $result['title']);
		$response->createFromTemplate("basic.tpl", $responseContent);
		return $response;
	}

	/**
	 * Get an article based on the ID
	 */
	private function getArticle($artid)
	{
		// get the crawler object
		try {
			$url = "https://www.nlm.nih.gov/medlineplus/spanish/ency/article/$artid.htm";
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
			$summary = $crawler->filter("div#ency_summary")->html();
			$summary = $this->utils->removeTildes($summary);
			$summary = preg_replace('#<a.*?>(.*?)</a>#i', '\1', $summary); // remove links
		} catch (exception $e) { }

		// get the body
		$body = "";
		try {
			$body = $crawler->filter("div.section-body")->html();
			$body = $this->utils->removeTildes($body); // remove tidles
			$body = preg_replace('#<a.*?>(.*?)</a>#i', '\1', $body); // remove links
		} catch (exception $e) { }

		// return
		return array('title' => $title, 'body' => $summary . $body);
	}
}
