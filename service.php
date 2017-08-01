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
			$response->setResponseSubject("Que desea buscar en Wikipedia?");
			$response->createFromTemplate("home.tpl", array());
			return $response;
		}

		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0987654321";
		self::$term = trim(strtolower($request->query));

		if (self::$term == '') self::$term = '-';

		$first = ucfirst(self::$term[0]);

		$url = "https://www.nlm.nih.gov/medlineplus/spanish/ency/encyclopedia_A.htm"; // default

		if (strpos($chars, $first) !== false) {
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
		}
		catch (exception $e) {
			$response = new Response();
			$response->setResponseSubject("DOCTOR: Estamos presentando problemas");
			$response->createFromText("Estamos presentando problemas con la enciclopedia m&eacute;dica. Por favor intente m&aacute;s tarde y contacte al soporte t&eacute;cnico.");
			return $response;
		}

		try {
			$result = $crawler->filter("a")->each(function ($node, $i) {
				if (strpos($node->attr('href'), "article/") !== false) {
					$text = strtolower(trim($node->text())); $text = htmlentities($text); $text = str_replace(array(
						'acute',
						'&',
						';',
						'tilde'), '', $text); $term = strtolower(trim(self::$term)); $term = str_replace(array(
						'acute',
						'&',
						';',
						'tilde'), '', $term); $simil = 0; $similx = similar_text($text, $term, $simil); if ($simil >= 60) {
						$art_id = $node->attr('href'); $art_id = str_replace(array('article/', '.htm'), '', $art_id); self::$similar_terms[$art_id] = $node->text(); }

					if ($simil > self::$max && $simil >= 85) {
						self::$max = $simil; self::$article = $node->attr('href'); }
				}
			}
			);
		}
		catch (exception $e) {
		}

		$artid = str_replace(array(
			'article/',
			'.htm',
			'./',
			'article'), '', self::$article);

		if (isset(self::$similar_terms[$artid]))
			unset(self::$similar_terms[$artid]);

		if (!is_null(self::$article)) {
			$result = $this->getArticle(self::$article);
			if ($result !== false) {
				$responseContent = array(
					"term" => $result['title'],
					"result" => $result['body'],
					"similars" => self::$similar_terms);

				// create the response
				$response = new Response();
				$response->setResponseSubject("Respuesta a su busqueda: " . self::$term);
				$response->createFromTemplate("basic.tpl", $responseContent);
				return $response;
			}
		}

		$response = new Response();
		$response->setResponseSubject("No se encontro respuesta a su busqueda: " . self::$term);
		$response->createFromTemplate("not_found.tpl", array("term" => self::$term, "similars" => self::$similar_terms));

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
		$result = $this->getArticle("article/" . $request->query . ".htm");

		if ($result === false) {
			$response = new Response();
			$response->setResponseSubject("No se encontro respuesta a su busqueda: " . self::$term);
			$response->createFromText("No se encontr&oacute; respuesta a su b&uacute;squeda: " . self::$term);
			return $response;
		}

		// create a json object to send to the template
		$responseContent = array("term" => $result['title'], "result" => $result['body']);

		// create the response
		$response = new Response();
		$response->setResponseSubject("Enciclopedia medica:" . $result['title']);
		$response->createFromTemplate("basic.tpl", $responseContent);

		return $response;
	}

	private function getArticle($path)
	{
		$artid = str_replace(array(
			'article/',
			'.htm',
			'./',
			'article'), '', $path);

		$client = new Client();
		$url = "https://www.nlm.nih.gov/medlineplus/spanish/ency/" . $path;

		try {
			$crawler = $client->request('GET', $url);
		}
		catch (exception $e) {
			return false;
		}

		$title = false;

		try {
			$title = $crawler->filter("div.page-title >h1")->text();
		}
		catch (exception $e) { }

		try {
			$result = $crawler->filter("div#d-article div.main")->html();
			try {
				self::$temp = array();

				$crawler->filter("div.sec-mb")->each(function ($node, $i) {
					self::$temp[] = $node->html(); }
				);

				foreach (self::$temp as $s) {
					$result = str_replace($s, '', $result);
				}
			}
			catch (exception $e) { }
		}
		catch (exception $e) {
			return false;
		}

		$result = strip_tags($result, "<a><h1><p><h2><h3><b><i><u><div><ul><li>");
		$result = str_replace("Hojee la enciclopedia", "", $result);

		if ($title === false)
			$title = strip_tags(substr($result, 0, 100));

		$articles = array();
		$p3 = 0;

		while (strpos($result, '<a href="./', $p3) !== false) {
			$p = strpos($result, '<a href="./', $p3);
			$p2 = strpos($result, '.htm">', $p);
			$p3 = strpos($result, '</a>', $p2);
			$art = substr($result, $p + 11, $p2 - $p - 11);
			if (is_numeric($art) && $art !== $artid) {
				$articles[$art] = substr($result, $p2 + 6, $p3 - $p2 - 6);
			}
		}

		// get a valid apretaste email address
		$utils = new Utils();
		$validEmailAddress = $utils->getValidEmailAddress();

		foreach ($articles as $art => $caption) {
			$result = str_replace("<a href=\"./$art.htm\">$caption</a>", "<alink href=\"mailto:$validEmailAddress?subject=DOCTOR ARTICULO $art\" target=\"_blank\">$caption</alink>",
				$result);
		}

		$result = strip_tags($result, "<alink><h1><p><h2><h3><b><i><u><div><ul><li>");
		$result = str_replace('<alink ', '<a ', $result);
		$result = str_replace('</alink>', '</a>', $result);

		return array('title' => $title, 'body' => $result);
	}
}
