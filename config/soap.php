<?php
	defined('SYSPATH') or die('No direct script access.');

	return array(
		/**
		 * - group_name = nazov konfiguracnej skupiny
		 *		- service = URL adresa pre servis
		 *			- authorization
		 *				- method = typ autorizacnej metody
		 *				- username = prihlasovacie meno
		 *				- password = prihlasovacie heslo
		 *				- callback = callback metoda pre autorizaciu
		 */
		'group_name' => array(
			'service' => '',
			'authorization' => array(
				'method' => SOAP_Authorization::WSSE,
				'username' => '',
				'password' => '',
				'callback' => ''
			)
		),
	);