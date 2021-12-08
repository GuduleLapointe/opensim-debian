<?php 
//*************************************************************************************
function ExtractValeur($chaine){
$posEgal = strpos($chaine,'=');
	if($posEgal === false)
	{
		$posEgal2 = strpos($chaine,']');
		$longueur = strlen($chaine);
		$ExtractValeur[0] = "CLES";
		$ExtractValeur[1] = substr($chaine, 1,$longueur - 3);
		return $ExtractValeur;
	}else
	{
		$longueur = strlen($chaine);
		$ExtractValeur[0] = trim(substr($chaine, 0, $posEgal - 1));
		$ExtractValeur[1] = trim(substr($chaine, $posEgal + 1));
		return $ExtractValeur;
	}
}			
//*************************************************************************************
function INI_Conf($cles,$valeur){
	$Version = "N.C";
	// *** Lecture Fichier .ini ***
	$filename2 = "config.ini";	// *
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {return "prb ini .<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb ini '.$filename.'<br>';}
	$Version = $tableauIni[$cles][$valeur];

	return $Version;
}
//*************************************************************************************
function INI_Conf_Moteur($cles,$valeur){
	$Version = "N.C";
	// *** Lecture Fichier .ini ***
	$filename2 = "moteurs.ini";	// *
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {return "prb ini .<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb ini '.$filename.'<br>';}
	$Version = $tableauIni[$cles][$valeur];

	return $Version;
}
//*************************************************************************************
function Version_INI_Conf(){
	$Version = "Version N.C";
	// *** Lecture Fichier .ini ***
	$filename2 = "config.ini";	// *
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {return "prb ini version.<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb ini version'.$filename.'<br>';}
	$Version = $tableauIni['Parametre_OSMW']['VersionOSMW'];

	return $Version;
}
//*************************************************************************************
function Destinataire_INI_Conf(){
	$destinataire = "destinataire N.C";
	// *** Lecture Fichier .ini ***
	$filename2 = "config.ini";	// *
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {return "prb ini destinataire.<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb ini destinataire'.$filename.'<br>';}
	$destinataire = $tableauIni['Parametre_OSMW']['destinataire'];

	return $destinataire;
}
//*************************************************************************************
function NbOpensim(){
	$nbOS = 1;
	// *** Lecture Fichier .ini ***
	$filename2 = "moteurs.ini";	// *
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {return "prb ini destinataire.<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb ini destinataire'.$filename.'<br>';}
	$nbOS = count($tableauIni);
	return $nbOS;
}
//*************************************************************************************
function Creation_ConfigINI(){
 
$cheminComplot = $_SERVER['SCRIPT_FILENAME'];
$chemin = explode("/", $cheminComplot);

	$fp = fopen ("config.ini", "w+");  
	fputs($fp,"[Parametre_OSMW]\r\n");
	fputs($fp,";******** Repertoire ou est installe OpenSim Manager Web ************\r\n");
	fputs($fp,"cheminAppli = /".$chemin[count($chemin)-2]."/\r\n");
	fputs($fp,";******** Destinataire des messages provenant de OSMW ************\r\n");
	fputs($fp,"destinataire = votreEmail@toto.com\r\n");
	fputs($fp,";******** Permet de creer un nb limité de sim 0 = Limité / 1 = NO Limit ************\r\n");
	fputs($fp,"Autorized = 1\r\n");
	fputs($fp,";******** Texte en haut a droite dans les menus de OSMW ************\r\n");
	fputs($fp,"VersionOSMW = Version N.C - ".$_SERVER['SERVER_NAME']."\r\n");
	fputs($fp,";******** Constante du fichier Regions.ini ************\r\n");
	fputs($fp,"CONST_InternalAddress = ".$_SERVER['SERVER_ADDR']."\r\n");
	fputs($fp,"CONST_AllowAlternatePorts = False\r\n");
	fputs($fp,"CONST_ExternalHostName = ".$_SERVER['SERVER_ADDR']."\r\n");
	fputs($fp,";******** Acces à la base de donnee du serveur ************ ; Non Utilisé actuellement\r\n");
	fputs($fp,"hostnameBDD = localhost\r\n");
	fputs($fp,"database =  Opensim\r\n");
	fputs($fp,"userBDD = login\r\n");
	fputs($fp,"passBDD =  password\r\n");
	fputs($fp,";******** Acces SSH du serveur ************\r\n");
	fputs($fp,"hostnameSSH = ".$_SERVER['SERVER_ADDR']."\r\n");
	fputs($fp,"usernameSSH =  login\r\n");
	fputs($fp,"passwordSSH = password\r\n");
	fclose ($fp);	
	
}
//*************************************************************************************
function Creation_MoteursINI(){
	$fp = fopen ("moteurs.ini", "w+");  
	fputs($fp,"[1]\r\n");
	fputs($fp,";******** Libellé du moteur doit etre le meme que pour le SCREEN lancé ************\r\n");
	fputs($fp,"name = Opensim_1\r\n");
	fputs($fp,";******** Libellé du moteur dans OSMW ************\r\n");	
	fputs($fp,"version = Version - Votre Region et ou vos sims\r\n");
	fputs($fp,";******** Chemin physique du moteur sur le serveur  ************\r\n");	
	fputs($fp,"address = /home/exemple/Opensim-0.7.1-Sim1/\r\n");
	fputs($fp,";******** Base de donnéé du moteur  ************\r\n");	
	fputs($fp,"DB_OS = Opensim\r\n");
	fclose ($fp);		 
}
//*************************************************************************************
function Creation_UsersINI(){
	$fp = fopen ("users.ini", "w+");  
	fputs($fp,"[root root]\r\n");
	fputs($fp,"pass = osmw\r\n");
	fputs($fp,"privilege = 4\r\n");
	fclose ($fp);		 
}
//*************************************************************************************



?>