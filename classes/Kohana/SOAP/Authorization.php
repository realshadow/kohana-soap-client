<?php
	defined('SYSPATH') or die('No direct script access.');

	/**
	 * Trieda na vytvorenie autorizacneho SOAP headeru. Momentalne je podporovane
	 * iba vytvorenie WSSE autentifikacie
	 *
	 * @package Utils\SOAP
	 * @author Lukas Homza <lhomza@cfh.sk>
	 * @version 1.0
	 */
	class Kohana_SOAP_Authorization {
		/** @var string - typ autorizacie */
		const WSSE = 'wsse';

		/** @var string - prihlasovacie meno */
		protected $username = null;
		/** @var string - prihlasovacie heslo */
		protected $password = null;
		/** @var string - autorizacny header */
		protected $header = null;

		/**
		 * Metoda na vytvorenie WSSE autorizacneho headeru
		 *
		 * @throws SOAP_Exception
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		protected function wsse() {
			if(empty($this->username)) {
				throw new SOAP_Exception('Unable to create WSSE header. Missing username from config.');
			}

			$wsseAuth = new SOAP_WSSE_Auth(
				new SoapVar($this->username, XSD_STRING, null, SOAP_WSSE_Auth::_NAMESPACE, null, SOAP_WSSE_Auth::_NAMESPACE),
				new SoapVar($this->password, XSD_STRING, null, SOAP_WSSE_Auth::_NAMESPACE, null, SOAP_WSSE_Auth::_NAMESPACE)
			);

			$soapWsseAuth = new SoapVar($wsseAuth, SOAP_ENC_OBJECT, null, SOAP_WSSE_Auth::_NAMESPACE, 'UsernameToken', SOAP_WSSE_Auth::_NAMESPACE);

			$wsseToken = new SOAP_WSSE_Token($soapWsseAuth);

			$wsseHeader = new SoapHeader(
				SOAP_WSSE_Auth::_NAMESPACE,
				'Security',
				new SoapVar($wsseToken, SOAP_ENC_OBJECT, null, SOAP_WSSE_Auth::_NAMESPACE, 'Security', SOAP_WSSE_Auth::_NAMESPACE),
				true
			);

			$this->header = $wsseHeader;
		}

		/**
		 * Konstruktor triedy. Nastavi potrebne premenne a urobi validaciu vstupnych dat.
		 * Nasledne zavola pozadovanu autorizacnu metodu
		 *
		 * @param array $config
		 *
		 * @throws SOAP_Exception
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		public function __construct(array $config) {
			$this->username = Arr::get($config, 'username');
			$this->password = Arr::get($config, 'password');

			$method = Arr::get($config, 'method');
			if(empty($method) || !method_exists(__CLASS__, $method)) {
				throw new SOAP_Exception('Authentification method :method does not exist.', array(
					':method' => $method
				));
			}

			$this->{$method}();
		}

		/**
		 * Metoda vrati autorizacny header
		 *
		 * @return \SoapHeader
		 *
		 * @since 1.0
		 */
		public function header() {
			return $this->header;
		}
	}