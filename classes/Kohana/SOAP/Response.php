<?php
	defined('SYSPATH') or die('No direct script access.');

	/**
	 * Trieda reprezentujuca odpoved zo SOAP servisu. Ponuka celistvejsi nahlad nad
	 * odpovedou zo servisu aby sa tymto vystupom zaroven priblizila co najblizsie
	 * Response objektom ci uz od Kohany alebo pre Rest
	 *
	 * V ramci triedy je mozne ziskat okrem ineho ziskat aj posledny request a response
	 * pre pripadne logovanie
	 *
	 * @package Utils\SOAP
	 * @author Lukas Homza <lhomza@cfh.sk>
	 * @version 1.0
	 */
	class Kohana_SOAP_Response {
		/** @var string - posledny vykonany request na servis */
		protected $lastRequest = null;
		/** @var string - posledna prijata odpoved zo servisu */
		protected $lastResponse = null;
		/** @var object - SoapFault object */
		protected $exception = null;
		/** @var string - body odpovede */
		protected $body = null;
		/** @var bool - nastala pri poslednom requested boolean alebo nie */
		protected $error = false;

		/**
		 * Konstruktor triedy, nastavi premenne a v pripade, ze pri poslednom
		 * requeste nastala chyba, pripravy dalsie premenne
		 *
		 * @param SoapClient $client
		 *
		 * @param SoapFault $response
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		public function __construct(SoapClient $client, $response) {
			$this->lastRequest = $client->__getLastRequest();
			$this->lastResponse = $client->__getLastResponse();

			# -- ak nastala chyba priprav veci
			if($response instanceof SoapFault) {
				$this->error = true;
				$this->exception = $response;
			} else {
				$this->body = $response;
			}
		}

		/**
		 * Metoda vrati posledny vykonany request na dany servis
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function lastRequest() {
			return $this->lastRequest;
		}

		/**
		 * Metoda vrati poslednu odpoved zo servisu
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function lastResponse() {
			return $this->lastResponse;
		}

		/**
		 * Metoda vrati boolean reprezentaciu toho, ci pri requeste nastala
		 * chyba alebo nie
		 *
		 * @return bool
		 *
		 * @since 1.0
		 */
		public function error() {
			return $this->error;
		}

		/**
		 * Metoda vrati SoapFault Exception object ak nastala pri poslednom
		 * requeste chyba
		 *
		 * @return \SoapFault
		 *
		 * @since 1.0
		 */
		public function exception() {
			return $this->exception;
		}

		/**
		 * Metoda vrati raw odpoved zo servisu
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function body() {
			return $this->body;
		}
	}