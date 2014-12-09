<?php
	defined('SYSPATH') or die('No direct script access.');

	/**
	 * Trieda vykonavanie SOAP requestov
	 *
	 * Autorizacia do servisov prebieha v ramci SOAP_Authorization triedy. Nastavit autorizaciu pre servis
	 * mozete v configu pre danu konfiguracnu skupinu. Nastavit je mozne username a password s pripadnou callback
	 * funkciou ak by trebalo vyskladat zlozitejsi autentifikacny header
	 *
	 * Trieda vzdy vrati SOAP_Response object, ktory reprezentuje odpoved zo servisu
	 *
	 * Priklad zavolania servisu:
	 *		$response = SOAP::instance($config_group)->call($url, $params);
	 *
	 * Priklad zavolania servisu s nastavenim cacheovania:
	 *		$response = SOAP::instance($config_group)->options('cache_wsdl', true)->call($url, $params);
	 *
	 * Priklad nastavenia vlastneho headeru a vyskladanie noveho sign parametra:
	 *		$response = SOAP::instance($config_group)->headers($namespace, $name, $data)->sign($params)->call($url, $params);
	 *		// alebo
	 *
	 * Priklad nastavenia viacerych headerov naraz:
	 *		$headers = array(
	 *			array(
	 *				'namespace' => $namespace,
	 *				'name' => $name,
	 *				'data' => $data',
	 *			),
	 *			array(
	 *				'namespace' => $namespace,
	 *				'name' => $name,
	 *			)
	 *		);
	 *		$response = SOAP::instance($config_group)->headers($headers)->sign($params)->call($url, $params);
	 *
	 * Priklad spracovania odpovede:
	 *		if($response->error()) {
	 *			throw $response->exception();
	 *			// alebo
	 *			print $response->exception()->getMessage();
	 *		} else {
	 *			return $response->body();
	 *		}
	 *
	 * @package Utils\SOAP
	 * @author Lukas Homza <lhomza@cfh.sk>
	 * @version 1.0
	 */
	class Kohana_SOAP_Client {
		/** @var array $instances - zoznam aktivnych instancii Rest triedy */
		protected static $instances = array();

		/** @var array $config - config konfiguracnej skupiny */
		protected $config = array();
		/** @var array $headers - zoznam SOAP headerov */
		protected $headers = array();
		/** @var array $options - nastavenia, ktore vstupuju do SoapClient konstruktoru */
		protected $options = array();
		/** @var array $authOutput - vystup z callback funkcie pre podpis */
		protected $authOutput = array();

		/**
		 * Staticky constructor pre triedu, ako vstup zoberie nazov skupiny z configu pre Soap. V pripade, ze
		 * nie je zadefinovana skupina bude hladat default skupinu. Nasledne vytvori novu instanciu
		 * triedy pre danu konfiguracnu skupinu a zresetuje headers pre pripad rozlicnych volani v ramci
		 * jednej instancie konfiguracnej skupiny
		 *
		 * @param string $group - nazov skupiny z configu
		 *
		 * @return \SOAP_Client
		 *
		 * @since 1.0
		 */
		public static function instance($group = 'default') {
			if(!isset(self::$instances[$group])) {
				$config = Kohana::$config->load('soap.'.$group);

				self::$instances[$group] = new self($config, $group);
			}

			# -- safety reset
			self::$instances[$group]->headers = array();

			return self::$instances[$group];
		}

		/**
		 * Konstruktor triedy, ktory na zaciatku skontroluje spravnu strukturu configu
		 * danej skupiny a nasledne nastavi defaultne options pre SoapClient-a.
		 *
		 * Defaultne nastavene options su:
		 *  - trace => true
		 *  - cache_wsdl => cacheovane v staging a production prostredi
		 *
		 * @param array $config - config konfiguracnej skupiny
		 * @param string $group - nazov konfiguracnej skupiny pre lepsiu validaciu
		 *
		 * @throws SOAP_Exception
		 *
		 * @since 1.0
		 */
		protected function __construct($config, $group) {
			if(empty($config)) {
				throw new SOAP_Exception('Missing config for service group :group.', array(
					':group' => $group
				));
			}

			$this->config = $config;

			if(empty($this->config['service'])) {
				throw new SOAP_Exception('Missing WSDL uri for service group :group.', array(
					':group' => $group
				));
			}

			$this->options = array(
				'trace' => true,
				'cache_wsdl' => (in_array(Kohana::$environment, array(Kohana::PRODUCTION, Kohana::STAGING)) ? WSDL_CACHE_MEMORY : WSDL_CACHE_NONE)
			);
		}

		/**
		 * Metoda na upravenie podpisu, ktory vstupuje do servisov. V pripade, ze do servisu nevstupuje
		 * iba username.password v tomto tvare je mozne zadefinovat aj callback metoda, kde sa da vstup
		 * do podpisu upravit.
		 *
		 * Do callback metody vstupuje:
		 *  - username = prihlasovacie meno
		 *  - password = prihlasovacie heslo
		 *  - params = pole parametrov, ktore vstupuje do sign metody a preposiela sa sem
		 *
		 * Metoda skontroluje spravnu strukturu vystup so sign callbacku. Vo vystupe sa musi vratit pole,
		 * ktore obsahuje:
		 *  - username
		 *  - password
		 *
		 * @param array $params
		 *
		 * @return \SOAP_Client
		 *
		 * @throws SOAP_Exception
		 *
		 * @since 1.0
		 */
		public function sign(array $params) {
			# -- zisti ci je nadefinovany callback
			$callback = Arr::path($this->config, 'authorization.callback');
			if(!empty($callback) && !is_callable($callback)) {
				throw new Kohana_Exception('Sign callback :callback is not callable.', array(
					':callback' => $callback
				));
			}

			# -- zavolaj callback
			$output = call_user_func_array($callback, array(
				Arr::path($this->config, 'authorization.username'),
				Arr::path($this->config, 'authorization.password'),
				$params
			));

			# -- skontroluj vystup
			if(!is_array($output)) {
				throw new SOAP_Exception('Output from sign callback must be an array, :type was returned.', array(
					':type' => gettype($output)
				));
			}

			if(!array_key_exists('username', $output) || !array_key_exists('password', $output)) {
				throw new SOAP_Exception('Username and/or password is missing from sign callback output');
			}

			$this->authOutput = array(
				'username' => Arr::get($output, 'username'),
				'password' => Arr::get($output, 'password'),
				'method' => Arr::path($this->config, 'authorization.method'),
			);

			return $this;
		}

		/**
		 * Metoda prida do zoznamu headerov novy header. V pripade, ze poslem ako prvy paramameter
		 * pole s headermi v tvare array(array('namespace' => '', ...)) tak sa pridaju vsetky headery k
		 * existujucim headerom.
		 *
		 * Vstupom do metody su rovnake parametre ako by ste posielali pri vytvarani SoapHeader-u.
		 * Ak prvy parameter nie je pole vsetky parametre, ktore vstupili do metody sa posunu
		 * priamo do konstruktoru pre SoapHeader. V pripade, ze je prvy parameter pole a zaroven
		 * bol poslany len tento jeden parameter vytiahnu sa prvku pola vsetky nastavenia a tie
		 * sa preposlu priamo do konstruktoru pre SoapHeader
		 *
		 * @return \SOAP_Client
		 *
		 * @since 1.0
		 */
		public function headers() {
			$headers = func_get_arg(0);

			if(is_array($headers) && func_num_args() === 1) {
				foreach($headers as $input) {
					$namespace = $name = $data = $actor = null;
					$mustunderstand = false;

					extract($input, EXTR_IF_EXISTS);

					# -- ak je posunuty aktor nikdy nesmie byt prazdny, neprejde ani null
					if(!empty($actor)) {
						$this->headers[] = new SoapHeader($namespace, $name, $data, $mustunderstand, $actor);
					} else {
						$this->headers[] = new SoapHeader($namespace, $name, $data, $mustunderstand);
					}
				}
			} else {
				$arguments = func_get_args();

				# -- keby som len mal PHP 5.6...
				# -- $instance = new SoapHeader(...$arguments);
				$reflection = new ReflectionClass('SoapHeader');
				$this->headers[] = $reflection->newInstanceArgs($arguments);

				unset($reflection);
			}

			return $this;
		}

		/**
		 * Metoda, ktora umozni nastavit options, ktore sa posuvaju do konstruktoru pre
		 * SoapClient PHP triedu. V pripade, ze poslem ako prvy paramameter pole s optionami
		 * v tvare array('option' => 'value') tak sa zmerguju s uz existujucimi optionami
		 *
		 * @param type $option
		 * @param type $value
		 *
		 * @return \SOAP_Client
		 *
		 * @since 1.0
		 */
		public function options($option, $value = null) {
			if(is_array($option)) {
				$this->options = array_merge($this->options, $option);
			} else {
				$this->options[$option] = $value;
			}

			return $this;
		}

		/**
		 * Metoda sluzi na zavolanie konkretnej metody z daneho servisu
		 *
		 * Na zaciatku sa sa vytvori pripojenie na servis, potom sa nastavi autorizacia za
		 * pomoci SOAP_Authorization triedy, nastavia sa headers a na konci sa vzdy vrati
		 * SOAP_Response object
		 *
		 * @param string $method - nazov volanej metody
		 * @param array $params - vstupne parametre
		 *
		 * @return \SOAP_Response
		 *
		 * @throws SOAP_Exception
		 *
		 * @since 1.0
		 */
		public function call($method, $params) {
			# -- vytvor clienta
			try {
				$client = new SoapClient($this->config['service'], $this->options);
			} catch(SoapFault $e) {
				throw new SOAP_Exception('Unable to connect to :service.', array(
					':service' => $this->config['service']
				));
			}

			$authConfig = $this->authOutput;
			if(empty($authConfig)) {
				$authConfig = Arr::get($this->config, 'authorization', null);
			}

			if(!is_null($authConfig)) {
				$auth = new SOAP_Authorization($authConfig);

				$this->headers[] = $auth->header();
			}

			# -- nastav headers
			$client->__setSoapHeaders($this->headers);

			if(Kohana::$profiling === true) {
				$calledOn = microtime();

				$benchmark = Profiler::start(__CLASS__, $method.'|'.$calledOn);
			}

			try {
				$result = $client->__soapCall($method, array($params));
			} catch(SoapFault $e) {
				$result = $e;
			}

			# -- priprav response object
			$response = new SOAP_Response($client, $result);

			if(isset($benchmark)) {
				if(Kohana::$config->load('debugger.enabled')) {
					list(, $calledBy) = debug_backtrace(false);

					$temp = array(
						$method.'|'.$calledOn => array(
							'service' => $this->config['service'],
							'request' => $response->lastRequest(),
							'response' => $response->lastResponse(),
							'called_by' => $calledBy
						)
					);

					Debugger::append(Debugger::PANEL_SOAP, $temp);
				}

				Profiler::stop($benchmark);
			}

			return $response;
		}
	}