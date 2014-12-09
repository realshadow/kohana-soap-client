<?php
	defined('SYSPATH') or die('No direct script access.');

	/**
	 * WSSE token pre php SOAP
	 *
	 * @package Utils\SOAP
	 * @author Lukas Homza <lhomza@cfh.sk>
	 * @version 1.0
	 */
	abstract class Kohana_SOAP_WSSE_Token {
		/** @var string - uzivatelsky token */
		private $UsernameToken;

		/**
		 * Konstruktor
		 *
		 * @params string $innerVal - token
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		function __construct($innerVal) {
			$this->UsernameToken = $innerVal;
		}
	}