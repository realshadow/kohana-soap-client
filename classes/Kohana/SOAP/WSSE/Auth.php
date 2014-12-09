<?php
	defined('SYSPATH') or die('No direct script access.');

	/**
	 * WSSE autorizacia pre php SOAP
	 *
	 * @package Utils\SOAP
	 * @author Lukas Homza <lhomza@cfh.sk>
	 * @version 1.0
	 */
	abstract class Kohana_SOAP_WSSE_Auth {
		/** @var string - XML namespace */
		const _NAMESPACE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

		/** @var string - uzivatelske meno */
		private $Username;
		/** @var string - uzivatelske heslo */
		private $Password;

		/**
		 * Konstruktor
		 *
		 * @param string $username - uzivatelske meno
		 * @param string $password - uzivatelske heslo
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		function __construct($username, $password) {
			$this->Username = $username;
			$this->Password = $password;
		}
	}