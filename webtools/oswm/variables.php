<?php

//******************************************************
// *******   CONSTANTES 
//******************************************************
$Couleur_Feux_V = "images/Feux_Vert.jpg";
$Couleur_Feux_O = "images/Feux_Orange.jpg";
$Couleur_Feux_R = "images/Feux_Rouge.jpg";

//******************************************************
// PARAMETRAGE DES COMMANDES DISPONIBLE POUR OSMANAGERWEB 
//******************************************************
// *** Lecture Fichier .ini ***
	$filename2 = "moteurs.ini";	// *
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {return "prb ini .<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb ini '.$filename.'<br>';}

$pre_cmd = "cd ".$tableauIni[$_SESSION['opensim_select']]['address'].";./ScreenSend ".$tableauIni[$_SESSION['opensim_select']]['name']." ";	// commande de base 
//***************************************
$cmd_show_stat = $pre_cmd."show stat"; 
$cmd_show_user = $pre_cmd."show user"; 
$cmd_forceupdate = $pre_cmd."force update"; 
$cmd_show_regions = $pre_cmd."show regions"; 
$cmd_start = "cd ".$tableauIni[$_SESSION['opensim_select']]['address'].";./RunOpensim.sh"; 
$cmd_stop = $pre_cmd."shutdown"; 
$cmd_restart = $pre_cmd."restart"; 
$cmd_etat_OS = "ps -e |grep mono";
$cmd_etat_OS2 = "screen -list";
$cmd_Version_mono = "mono -V";
$cmd_Delete_log = "cd ".$tableauIni[$_SESSION['opensim_select']]['address'].";chmod 777 OpenSim.log;rm OpenSim.log";
$cmd_force_update = $pre_cmd."force update"; 		
$cmd_Delete_file = "cd ".$tableauIni[$_SESSION['opensim_select']]['address'].";chmod 777 ";
$cmd_save_iar = $pre_cmd."save iar ";
$cmd_region_root = $pre_cmd."change region root"; 
//*************************************************************************************

	$filename2 = "config.ini";	// *
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {return "prb ini .<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb ini '.$filename.'<br>';}

$MENU_LATTERALE = '<div id="menu"><ul>
		<li><a href="./" title="Page d\'accueil"><span>Accueil</span></a></li>
		<li><a href="?a=1" title="Gestion des sims (Messages, Start, Stop)"><span>Sims</span></a></li>
		<li><a href="?a=2" title="Gestion des sauvegardes (OAR, XML2)"><span>Backup</span></a></li>
		<li><a href="?a=3" title="Gestion du Terrain (RAW, JPG)"><span>Terrain</span></a></li>
		<li><a href="?a=7" title="Gestion et Visualisation du Log"><span>Log</span></a></li>
		<li><a href="?a=10" title="Gestion des fichiers de sauvegardes (OAR, IAR, RAW, ...)"><span>Fichiers</span></a></li>
		<li><a href="?a=9" title="Vous avez un probleme, envoyer un mail au gestionnaire du serveur"><span>Contact</span></a></li>
		<li><a href="?a=11" title="Affichage des sims presentes sur le moteur"><span>Carte</span></a></li>
		<li><a href="?a=14" title="Qui a participe au projet OSMW"><span>A Propos</span></a></li>
		<li><a href="?a=13" title="Une question, ici peut etre la reponse !"><span>Aide</span></a></li>
	</ul></div>';

$PIED_DE_PAGE = '<hr><center>'.$tableauIni['Parametre_OSMW']['VersionOSMW'].'</center>';
?>


