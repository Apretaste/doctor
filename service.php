<?php

use Goutte\Client;

class DoctorService extends ApretasteService
{

    static $term = null;

    static $article = null;

    static $max = 0;

    static $temp = [];

    static $similar_terms = [];

    /**
     * Function executed when the service is called
     */
    public function _main()
    {
        $this->response->setLayout('sms.ejs');

        // do not allow blank searches
        if (empty($this->request->input->data->query)) {
            $this->response->setTemplate("home.ejs", []);

            return;
        }

        // lower case and remove tildes for the term
        self::$term = trim(strtolower($this->request->input->data->query));

        // get the right URL to pull info
        $url = "https://medlineplus.gov/spanish/healthtopics_a.html";
        $first = self::$term[0];
        if (strpos("ABCDEFGHIJKLMNOPQRSTUVWXYZ0987654321", strtoupper($first)) !== false) {

            if ($first == 'x' || $first == 'y' || $first == 'z') {
                $first = 'xyz';
            }

            if (strpos("0987654321", $first) !== false) {
                $url = "https://medlineplus.gov/spanish/healthtopics_0-9.html";
            } else {
                $url = "https://medlineplus.gov/spanish/healthtopics_{$first}.html";
            }
        }

        $client = new Client();
        $guzzle = $client->getClient();

        // create a crawler
        try {
            $crawler = $client->request('GET', $url);
        } catch (exception $e) {
            // Send an error notice to programmer
            Utils::createAlert("DOCTOR: Error al leer resultados: ".self::$term.". ".$e->getMessage(), "ERROR");

            // respond to user
            $this->simpleMessage("DOCTOR: Estamos presentando problemas",
                "Estamos presentando problemas con el doctor. Por favor intente m&aacute;s tarde. Hemos avisado al personal tecnico para corregir este error.");

            return;
        }

        try {
            $result = $crawler->filter("a")->each(function ($node, $i) {
                if (strpos($node->attr('href'), "https://medlineplus.gov/spanish/") !== false
                    && strpos($node->attr('href'), "https://medlineplus.gov/spanish/healthtopics_") === false) {

                    $text = strtolower($node->text());

                    // add all similar terms to the list of similar
                    similar_text($text, self::$term, $simil);
                    if ($simil >= 60) {
                        $art_id = $node->attr('href');
                        $art_id = str_replace(['https://medlineplus.gov/spanish/', '.html'], '', $art_id);
                        self::$similar_terms[$art_id] = $node->text();
                    }

                    // find the term that is closer to the text passed
                    if ($simil > self::$max && $simil >= 85) {
                        self::$max = $simil;
                        self::$article = $node->attr('href');
                    }
                }
            });
        } catch (exception $e) {
        }

        // remove the current ID from the list of similar terms
        $artid = str_replace(['https://medlineplus.gov/spanish/', '.html', './', 'article'], '', self::$article);
        if (isset(self::$similar_terms[$artid])) {
            unset(self::$similar_terms[$artid]);
        }

        // respond with error if article not found
        if (empty(self::$article)) {
            $this->response->setTemplate("not_found.ejs", [
                "term"     => self::$term,
                "similars" => self::$similar_terms
            ]);

            return;
        }

        // get the article ID of the term selected
        $article = $this->getArticle($artid);

        // create array to send info to the view
        $responseContent = [
            "term"     => $article['title'],
            "result"   => $article['body'],
            "similars" => self::$similar_terms
        ];

        // create the response
        $this->response->setTemplate("basic.ejs", $responseContent);
    }

    /**
     * Subservice DOCTOR ARTICULO ######
     *
     */
    public function _articulo()
    {
        $this->response->setLayout('sms.ejs');

        $result = $this->getArticle($this->request->input->data->query);

        if (empty($result)) {
            $this->simpleMessage("No se encontro respuesta a su busqueda: ".self::$term,
                "No se encontr&oacute; respuesta a su b&uacute;squeda: ".self::$term);

            return;
        }

        // create an object to send to the template
        $responseContent = [
            "term"     => $result['title'],
            "result"   => $result['body'],
            "similars" => []
        ];

        // create the response
        $this->response->setTemplate("basic.ejs", $responseContent);
    }

    /**
     * Get an article based on the ID
     */
    private function getArticle($artid)
    {
        // get the crawler object
        try {
            $url = "https://medlineplus.gov/spanish/$artid.html";
            $client = new Client();
            $crawler = $client->request('GET', $url);
        } catch (exception $e) {
            return false;
        }

        // get the title
        $title = "";
        try {
            $title = $crawler->filter("div.page-title >h1")->text();
        } catch (exception $e) {
        }

        // get the summary
        $summary = "";
        try {
            $summary = $crawler->filter("div#ency_summary")->html();
            $summary = preg_replace('#<a.*?>(.*?)</a>#i', '\1', $summary); // remove links
        } catch (exception $e) {
        }

        // get the body
        $body = "";
        try {
            $body = $crawler->filter("div.section-body")->html();
            $body = preg_replace('#<a.*?>(.*?)</a>#i', '\1', $body); // remove links
        } catch (exception $e) {
        }

        // return
        return ['title' => $title, 'body' => $summary.$body];
    }
}
