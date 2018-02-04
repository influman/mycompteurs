<?php
   $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>";      
   //***********************************************************************************************************************
   // V1.0 : Script qui permet la gestion de 20 compteurs, avec remise à zéro automatisable toutes les n périodes
   //*************************************** API eedomus ******************************************************************
   $maxcompteurs = 20;
   $mycompteurs = array();
   $mylastcpttime = array();
   $mytimecpttype = array();
   
   // recuperation des infos depuis la requete
   // action : xml, raz, increment, decrement
   $actioncpt=getArg("action", $mandatory = true, $default = '');

   // c'est l'appel en mode capteur, format XML, qui génére les éventuelles remises à zéro automatique
   // Pour un compteur, raz par exemple, "toutes les 10 mn" : razfreq = "Minute", raznum = 10
   if ($actioncpt == "xml") {
	// razfreq : manuel, minute, heure, jour, semaine, mois, annee
  	 $razfreq=getArg("raz", $mandatory = true, $default = 'Manuel');
        // raznum : 1 à n
 	 $raznum=getArg("nbraz", $mandatory = true, $default = 1);
   }

  // n° de compteur : 1 à 20
  $compteur=getArg("cpt", $mandatory = true, $default = 1);
  // pas d'incrémentation : libre
  $step=1;
  $step=getArg("pas", $mandatory = false, $default = 1);
   
   // Lecture des compteurs en variable générale de l'eedomus. 
   // question, ces variables restent-elles en cas de reboot de la box ? synchro avec le cloud ?
   for ($i = 1; $i <= $maxcompteurs; $i++) {
	if (loadVariable('myCompteur'.$i) !='') {
		$mycompteurs[$i]=loadVariable('myCompteur'.$i);
		$mylastcpttime[$i]=loadVariable('myLastCptTime'.$i);
		$mytimecpttype[$i]=loadVariable('myTimeCptType'.$i);
	} else {
		// mise à zéro générale la première fois
		$mycompteurs[$i]=0;
		$mylastcpttime[$i]=0;
		$mytimecpttype[$i]="";
	}
   }

   // Mise à jour du compteur désigné
   $valeurcpt = $mycompteurs[$compteur];
   if ($actioncpt == "increment") {
	$valeurcpt = $valeurcpt + $step;
	$mycompteurs[$compteur] = $valeurcpt;
   }
   if ($actioncpt == "decrement") {
	$valeurcpt = $valeurcpt - $step;
	$mycompteurs[$compteur] = $valeurcpt;
   }
   if ($actioncpt == "raz") {
	$valeurcpt = 0;
	$mycompteurs[$compteur] = $valeurcpt;
   }

   if ($actioncpt == "xml") {
 	  // donnée actuelle à retenir en fonction de la fréquence de mesure du compteur appelé
 	  switch(strtolower($razfreq))
  	 {
		case 'minute':
			$ActualFreq = date('i');
    		  break;
		case 'heure':
			$ActualFreq = date('G');
    		  break;
		case 'jour':
			$ActualFreq = date('d');
  		  break;
		case 'semaine':
			$ActualFreq = date('W');
  		  break;
 		case 'mois':
			$ActualFreq = date('m');
		  break;
  		case 'année':
			$ActualFreq = date('Y');
		  break;
		default:
			$ActualFreq = 'Manuel';
   	  }

	 // si le compteur a changé de type de fréquence en argument, on change son type sans le mettre à zéro automatiquement	
	  if ($mytimecpttype[$compteur] <> $razfreq) {
		$mylastcpttime[$compteur]=$ActualFreq;
		$mytimecpttype[$compteur]=$razfreq;
	  }
	 // Vérification si raz automatique du compteur
	 if ($ActualFreq != 'Manuel') {
		$calcFreq = $ActualFreq;		
		if ($mylastcpttime[$compteur] > $calcFreq) {
			switch(strtolower($razfreq))
			{
			case 'minute':
				$calcFreq += 60;
				break;
			case 'heure':
				$calcFreq += 24;
				break;
			case 'semaine':
				$calcFreq += 52;
				break;
			case 'mois':
				$calcFreq += 12;
				break;
			case 'jour':
				if (date('m') == 3 || date('m') == 5 || date('m') == 7 || date('m') == 10 || date('m') == 12) {
					$calcFreq += 30;
				}
				if (date('m') == 2 || date('m')== 4 || date('m') == 6 || date('m') == 8 || date('m') == 9 || date('m') == 11 || date('m') == 1) {
					$calcFreq += 31;
				}
				if (date('m') == 3) {
					$calcFreq += 28;
				}
				break;
			}
		}
		$delta = $calcFreq - $mylastcpttime[$compteur];
		// la période est écoulée alors remise à zéro
		if ($delta >= $raznum) {
			$mylastcpttime[$compteur]=$ActualFreq;
			$mycompteurs[$compteur]=0;
		} 
   
     }
   }
    // rechargement général en mémoire et génération du XML avec tous les compteurs
    $xml="<COMPTEURS>";
    for ($i = 1; $i <= $maxcompteurs; $i++) {
		saveVariable('myCompteur'.$i,$mycompteurs[$i]);
		saveVariable('myTimeCptType'.$i,$mytimecpttype[$i]);
		saveVariable('myLastCptTime'.$i,$mylastcpttime[$i]);
		$xml .= "<CPT".$i.">".$mycompteurs[$i]."</CPT".$i.">";
		$xml .= "<TIME".$i.">".$mylastcpttime[$i]."</TIME".$i.">";
		$xml .= "<TYPE".$i.">".$mytimecpttype[$i]."</TYPE".$i.">";
	}

	$xml .= "</COMPTEURS>";
	sdk_header('text/xml');
	if ($actioncpt == "xml") {
		echo $xml;
	}
?>