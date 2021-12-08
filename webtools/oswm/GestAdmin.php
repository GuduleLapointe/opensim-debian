<?php 
include 'variables.php';

if (session_is_registered("authentification") && $_SESSION['privilege']==4){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Gestion de la configuration de OpenSim Manager Web.</B>';
	$ligne2 = '*** <u>Moteur OpenSim selectionne: </u>'.INI_Conf_Moteur($_SESSION['opensim_select'],"name").' - '.INI_Conf_Moteur($_SESSION['opensim_select'],"version").' ***';
	echo '<div class="block" id="clean-gray"><button><CENTER>'.$ligne1.'<br>'.$ligne2.'</CENTER></button></div>';
	echo '<hr>';
	//******************************************************
	$btnN1 = "disabled"; $btnN2 = "disabled"; $btnN3 = "disabled";
	if( $_SESSION['privilege']==4){$btnN1="";$btnN2="";$btnN3="";}		//  Niv 4	
	if( $_SESSION['privilege']==3){$btnN1="";$btnN2="";$btnN3="";}		//  Niv 3
	if( $_SESSION['privilege']==2){$btnN1="";$btnN2="";}				//	Niv 2
	if( $_SESSION['privilege']==1){$btnN1="";}							//	Niv 1
	//******************************************************	
//*******************************************************************	

//*****************************************************************
if($_POST['cmd'])
{
	// *** Affichage mode debug ***

	echo $_POST['cmd'].'<br>';

		if($_POST['cmd'] == 'Ajouter')
		{
			
		} 
		if($_POST['cmd'] == 'Enregistrer')
		{	
			
		} 
		if($_POST['cmd'] == 'Modifier')
		{
		
		} 
		if($_POST['cmd'] == 'Supprimer')
		{			

		} 
}
//******************************************************

echo 'Bienvenue, ici tous les parametres de chaque fichier ini propre a OSMW.<br>';

/*
echo '<br>Opensim_1: '.INI_Conf_Moteur("1","name");
echo '<br>Opensim_1: '.INI_Conf_Moteur("1","address");
echo '<br>Opensim_1: '.INI_Conf_Moteur("1","version");
echo '<br>Opensim_2: '.INI_Conf_Moteur("2","name");
echo '<br>Opensim_2: '.INI_Conf_Moteur("2","address");
echo '<br>Opensim_2: '.INI_Conf_Moteur("2","version");

[Parametre_OSMW]
cheminAppli = /OSMW/
destinataire = fgagod@gmail.com
Autorized = 1
VersionOSMW = V 1.1 Beta
CONST_InternalAddress = 88.191.137.244
CONST_AllowAlternatePorts = False
CONST_ExternalHostName = 88.191.137.244
hostnameBDD = localhost
database =  Opensim
userBDD = root
passBDD =  ****
hostnameSSH = 88.191.137.244
usernameSSH = fgagod
passwordSSH = ****
*/
//***********************************************************************************************
//******************************** CONFIG.INI **************************************************
//***********************************************************************************************
echo '<hr><b><u>Fichier config.ini</u></b><br>';	
	echo '<br>Version OSMW: '.INI_Conf("Parametre_OSMW","VersionOSMW");
	echo '<br>Destinataire: '.INI_Conf("Parametre_OSMW","destinataire");
	echo '<br>cheminAppli: '.INI_Conf("Parametre_OSMW","cheminAppli");
	echo '<br>Autorized: '.INI_Conf("Parametre_OSMW","Autorized");
	echo '<br>';
	echo '<br>CONST_InternalAddress: '.INI_Conf("Parametre_OSMW","CONST_InternalAddress");
	echo '<br>CONST_AllowAlternatePorts: '.INI_Conf("Parametre_OSMW","CONST_AllowAlternatePorts");
	echo '<br>CONST_ExternalHostName: '.INI_Conf("Parametre_OSMW","CONST_ExternalHostName");	
	echo '<br>';
	echo '<br>hostnameSSH: '.INI_Conf("Parametre_OSMW","hostnameSSH");
	echo '<br>usernameSSH: '.INI_Conf("Parametre_OSMW","usernameSSH");
	echo '<br>passwordSSH: '.INI_Conf("Parametre_OSMW","passwordSSH");
	echo '<br>';
	echo '<br>hostnameBDD: '.INI_Conf("Parametre_OSMW","hostnameBDD");
	echo '<br>database: '.INI_Conf("Parametre_OSMW","database");
	echo '<br>userBDD: '.INI_Conf("Parametre_OSMW","userBDD");
	echo '<br>passBDD: '.INI_Conf("Parametre_OSMW","passBDD");	
	echo '<br>';	
//***********************************************************************************************	

//***********************************************************************************************
//******************************** MOTEURS.INI **************************************************
//***********************************************************************************************
echo '<hr><b><u>Fichier moteurs.ini</u></b><br>';	
	// *** Lecture Fichier .ini ***
	$filename2 = "/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."moteurs.ini";	
	if (file_exists($filename2)) 
		{$filename = $filename2 ;}else {return "prb ini .<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	while (list($key) = each($tableauIni))
		{echo '<br>Moteur: '.$key.' - '.INI_Conf_Moteur($key,"name").'<br> - '.INI_Conf_Moteur($key,"version").'<br> - Emplacement: '.INI_Conf_Moteur($key,"address").'<br>';}
	echo '<br>Nb Moteur Opensim: '.NbOpensim();	
	echo '<br>';
//***********************************************************************************************	

//***********************************************************************************************
//******************************** USERS.INI **************************************************
//***********************************************************************************************
echo '<hr><b><u>Fichier users.ini</u></b><br>';	
	// *** Lecture Fichier .ini ***
	$filename2 = "/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."users.ini";	
	if (file_exists($filename2)) 
		{$filename = $filename2 ;}else {return "prb ini .<br>";}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	while (list($key, $val) = each($tableauIni))
	{
			$privilege = $tableauIni[$key]['privilege'];
			echo '<br>Nom: '.$key.' Niv: '.$privilege;
	}
//***********************************************************************************************	
	echo '<hr>';
//******************************************************				
}else{header('Location: index.php');   }
?>