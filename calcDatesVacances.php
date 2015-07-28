<?php
/**
 * calcDatesVacances.php
 * 
 * @auteur     marc laville
 * @Copyleft 2014
 * @date       04/11/2014
 * @version    0.5
 *
 * @date revision 27/07/2015  contournement du bug du au changement de zone (éte 2015)
 *
 * Traitement de la source http://cache.media.education.gouv.fr/ics/Calendrier_Scolaire_Zone_X.ics ( X <- A, B ou C )
 *
 * Licensed under the GPL license:
 *   http://www.opensource.org/licenses/mit-license.php
 */

function traiteElement( $item ) {
	$arrPlage = array();
	$matches = null;

	if(preg_match('/DTSTART;VALUE=DATE:([0-9]{8})/', $item, $matches)) {
		$arrPlage["DTSTART"] = $matches[1];
	}
	if(preg_match('/DTEND;VALUE=DATE:([0-9]{8})/', $item, $matches)) {
		$arrPlage["DTEND"] = $matches[1];
	}
	if(preg_match('/SUMMARY:(.*) - Zone ([A-C])/', $item, $matches)) {
		$arrPlage["DESCRIPTION"] = $matches[1];
		$arrPlage["Zone"] = $matches[2];
	}

	return $arrPlage;
}

function plageValide( $item ) {
	return count( $item ) == 4;
}

function listVacances( $fileName ) {
	// On charge le contenu du fichier
	//  puis il est décomposé en items séparés par '/BEGIN:VEVENT/'
	$result = array_map("traiteElement", preg_split('/BEGIN:VEVENT/', file_get_contents($fileName)));
	
	for( $i = count( $result ) - 1 ; $i >= 0 ; $i-- ) {
		if( !count( $result[$i] ) ) {
			unset( $result[$i] );
		} else {
			if( $result[$i]['DESCRIPTION'] == 'Rentrée scolaire des élèves' && $i > 2 ) {
				$result[$i - 2]['DTEND'] = $result[$i]['DTSTART'];
				unset( $result[$i] );
			}
		}
	}

	$retour = array_values( array_filter( $result, "plageValide" ) );
	
	/*
	 * Verrue à cause des changements de zone
	 */
//	$retour[] = Array ( 'DTSTART' => "20150704", "DESCRIPTION" => "Vacances d'été", "Zone" => "A", "DTEND" => "20150901" );
	array_unshift ( $retour, Array ( 'DTSTART' => "20150704", "DESCRIPTION" => "Vacances d'été", "Zone" => "A", "DTEND" => "20150901" ) );
	
	return $retour;
}

function borneDateArray( $unItem ) {
//	return array( DateTime::createFromFormat( 'Ymd', $unItem['DTSTART'] ), DateTime::createFromFormat( 'Ymd', $unItem['DTEND'] ) );
	return array( date_create_from_format( 'Ymd', $unItem['DTSTART'] ), date_create_from_format( 'Ymd', $unItem['DTEND'] ) );
}

function borneDateObject( $unItem ) {
	return array( date_create_from_format( 'Ymd', $unItem->DTSTART  ), date_create_from_format( 'Ymd', $unItem->DTEND ) );
}

function intersectPlageMois( $item, $unMois ) {

	date_default_timezone_set('Europe/Paris');

//	$date01 = DateTime::createFromFormat('Ym-d', $unMois . '-01');
	$date01  = date_create_from_format('Ym-d', $unMois . '-01');

//	$dateDerJour = DateTime::createFromFormat('Ym-d', strval( intval($unMois) + 1 ) . '-00');
	$dateDerJour  = date_create_from_format('Ym-d', strval( intval($unMois) + 1 ) . '-00');
	
	$borneDate = (is_array($item)) ? borneDateArray( $item ) : borneDateObject( $item );
	list($dateDebut, $dateFin) = $borneDate;
//			 $dateInterval = new DateInterval('P1D') ;
	$dateInterval = date_interval_create_from_date_string('1 day') ;
	date_add($dateDebut, $dateInterval);
	date_sub($dateFin, $dateInterval);
	$result = array();
	
	if( $dateFin >= $date01 && $dateDebut <= $dateDerJour ) {
		for( $dateMin = ( $date01 > $dateDebut ) ? $date01 : $dateDebut,
			 $dateMax = ( $dateDerJour > $dateFin ) ? $dateFin : $dateDerJour;
			 $dateMin <= $dateMax ;
//			 $dateMin->add($dateInterval) ) {
			date_add($dateMin, $dateInterval) ) {
//				$result[] = intval( $dateMin->format('d') );
				$result[] = intval( date_format ( $dateMin, 'd' ) );
		}
	}

	return $result;
}

function listJoursVacances( $unMois, $uneZone ) {

	$nomFicBuffer = './buf/plageVacances_' . $uneZone . '.json';
	$dateFicJson = null;
	$ageFicJson = null;
	
	if( file_exists($nomFicBuffer) ){
		$dateFicJson = date( "F d Y H:i:s", filemtime($nomFicBuffer) );
//		$objDateTimeFic = new DateTime($dateFicJson);
		$objDateTimeFic = date_create($dateFicJson);
		
//		$ageFicJson = $objDateTimeFic->diff( new DateTime('NOW') );
		$ageFicJson = date_diff ( $objDateTimeFic , date_create( ) );
	}
	$nbHeures = is_null($ageFicJson) ? 1 : $ageFicJson->h;
	
	$nomFic = "http://cache.media.education.gouv.fr/ics/Calendrier_Scolaire_Zone_" . $uneZone . ".ics";
	if( $nbHeures > 0 ) {
		$listPlages = listVacances( $nomFic );
		
	//	$data = mb_convert_encoding(json_encode( $listPlages ), 'UTF-8', 'OLD-ENCODING');
	//	file_put_contents( './plageVacances.json', $data, LOCK_EX);
		file_put_contents( $nomFicBuffer, json_encode( $listPlages ), LOCK_EX );
//		file_put_contents( $nomFicBuffer, json_encode($listPlages)), LOCK_EX );
	} else {
		$listPlages = json_decode( file_get_contents($nomFicBuffer) );
	}


	$listJour = array();

	foreach ($listPlages as &$plage) {
		$listJour = array_merge($listJour, intersectPlageMois( $plage, $unMois ));
	}

	return array( 'dateFicJson'=>$dateFicJson,
//				   'ageFicJson'=>$ageFicJson,
				   'nbHeures'=>$nbHeures,
				   'result'=>$listJour,
				   'origin'=>$nomFic
				);
}

?>