<?php
/**
 * srv_datesVacances.php
 * 
 * @auteur     marc laville
 * @Copyleft 2014
 * @date       04/11/2014
 * @version    0.4
 *
 * @date revision xx/xx/xxxx 
 *
 * Déclaration d'une classe qui contiendra les méthodes de Service WEB, et instanciation de la classe SoapServer
 * pour rendre notre Service disponible
 *
 * Licensed under the GPL license:
 *   http://www.opensource.org/licenses/mit-license.php
 */

//Cette classe pourra contenir d'autres méthodes accessibles via le SoapServer

include 'calcDatesVacances.php';

class DateServer{

	// Point d'entée du WebService
	// On déclare notre méthode qui renverra la réponse au client
	function getDatesVacancesBasic($param, $zone, $adr) {
		file_put_contents( '../log/dvs.txt', date_format ( date_create( ), 'Y-m-d H:i:s#' ) . $adr . PHP_EOL, FILE_APPEND | LOCK_EX );

		$returnedValue = listJoursVacances( $param, $zone );

		return $returnedValue['result'];
	}
	
	// Point d'entée du WebService
	// On déclare notre méthode qui renverra la réponse au client
	function getDatesVacances($param, $zone) {
		$returnedValue = array();
//		$objDateTimeRef = new DateTime('NOW');
		$objDateTimeRef = date_create( );

		$returnedValue['result'] = listJoursVacances( $param, $zone );
		$returnedValue['zone'] = $zone;

//		$interval = $objDateTimeRef->diff( new DateTime('NOW') );
		$interval = date_diff( $objDateTimeRef , date_create( ) );
		$returnedValue['exectime'] = 60 * $interval->i + $interval->s;

		return $returnedValue;
	}
	} // Fin class DateServer

//Cette option du fichier php.ini permet de ne pas stocker en cache le fichier WSDL, afin de pouvoir faire nos tests
//Car le cache se renouvelle toutes les 24 heures, ce qui n'est pas idéal pour le développement
ini_set('soap.wsdl_cache_enabled', 0);

//L'instanciation du SoapServer se déroule de la même manière que pour le client : voir la doc pour plus d'informations sur les 
//Différentes options disponibles 
$serversoap = new SoapServer("./wsdl/datesVacances.wsdl");

//Ici nous déclarons la classe qui sera servie par le Serveur SOAP, c'est cette déclaration qui fera le coeur de notre Servie WEB.
//Je déclare que je sers une classe contenant des méthodes accessibles, on peut aussi déclarer plus simplement des fonctions
//par l'instruction addFunction() : $serversoap->addFunction("retourDate"); à ce moment-là nous ne faisons pas de classe.

//Noter le style employé pour la déclaration : le nom de la classe est passé en argument de type String, et non pas de variable...
$serversoap->setClass("DateServer");

//Ici, on dit très simplement que maintenant c'est à PHP de prendre la main pour servir le Service WEB : il s'occupera de l'encodage XML, des
//Enveloppes SOAP, de gérer les demandes clientes, etc. Bref, on en a fini avec le serveur SOAP !!!!
$serversoap->handle();
?>