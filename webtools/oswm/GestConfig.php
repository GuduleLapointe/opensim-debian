<?php 
include 'variables.php';

if (session_is_registered("authentification") && $_SESSION['privilege']>=3){ // v&eacute;rification sur la session authentification 
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
	// *******************************************************************
	// *************** ACTION BOUTON *************************************
	// *******************************************************************

	// On charge le fichier INI
	$filename2 = "/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."config.ini";

	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	
	if($_POST['cmd'] == 'Enregistrer')
	{	
	while (list($key, $val) = each($tableauIni))
		{
			// AJOUTER chaque valeur
			$tableauIni[$_POST['NewName']]['cheminAppli'] = $_POST['cheminAppli'];
			$tableauIni[$_POST['NewName']]['destinataire'] = $_POST['destinataire'];
			$tableauIni[$_POST['NewName']]['Autorized'] = $_POST['Autorized'];
			$tableauIni[$_POST['NewName']]['VersionOSMW'] = $_POST['VersionOSMW'];
			$tableauIni[$_POST['NewName']]['CONST_InternalAddress'] = $_POST['CONST_InternalAddress'];
			$tableauIni[$_POST['NewName']]['CONST_AllowAlternatePorts'] = $_POST['CONST_AllowAlternatePorts'];
			$tableauIni[$_POST['NewName']]['CONST_ExternalHostName'] = $_POST['CONST_ExternalHostName'];
			$tableauIni[$_POST['NewName']]['hostnameBDD'] = $_POST['hostnameBDD'];
			$tableauIni[$_POST['NewName']]['database'] = $_POST['database'];
			$tableauIni[$_POST['NewName']]['userBDD'] = $_POST['userBDD'];
			$tableauIni[$_POST['NewName']]['passBDD'] = $_POST['passBDD'];
			$tableauIni[$_POST['NewName']]['hostnameSSH'] = $_POST['hostnameSSH'];
			$tableauIni[$_POST['NewName']]['usernameSSH'] = $_POST['usernameSSH'];
			$tableauIni[$_POST['NewName']]['passwordSSH'] = $_POST['passwordSSH'];
			// Enregistrement du nouveau fichier 
			$fp = fopen ("/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."config.ini", "w");  
				fputs($fp,"[".$key."]\r\n");
				fputs($fp,"cheminAppli  = ".$tableauIni[$key]['cheminAppli']."\r\n");
				fputs($fp,"destinataire  = ".$tableauIni[$key]['destinataire']."\r\n");
				fputs($fp,"Autorized  = ".$tableauIni[$key]['Autorized']."\r\n");
				fputs($fp,"VersionOSMW  = ".$tableauIni[$key]['VersionOSMW']."\r\n");
				fputs($fp,"CONST_InternalAddress  = ".$tableauIni[$key]['CONST_InternalAddress']."\r\n");
				fputs($fp,"CONST_AllowAlternatePorts  = ".$tableauIni[$key]['CONST_AllowAlternatePorts']."\r\n");
				fputs($fp,"CONST_ExternalHostName  = ".$tableauIni[$key]['CONST_ExternalHostName']."\r\n");
				fputs($fp,"hostnameBDD  = ".$tableauIni[$key]['hostnameBDD']."\r\n");
				fputs($fp,"database  = ".$tableauIni[$key]['database']."\r\n");
				fputs($fp,"userBDD  = ".$tableauIni[$key]['userBDD']."\r\n");
				fputs($fp,"passBDD  = ".$tableauIni[$key]['passBDD']."\r\n");
				fputs($fp,"hostnameSSH  = ".$tableauIni[$key]['hostnameSSH']."\r\n");
				fputs($fp,"usernameSSH  = ".$tableauIni[$key]['usernameSSH']."\r\n");
				fputs($fp,"passwordSSH  = ".$tableauIni[$key]['passwordSSH']."\r\n");
			fclose ($fp);  
		}
	}
}
//******************************************************

	// *** Lecture Fichier config.ini ***
	$filename2 = "/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."config.ini";	
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	
	while (list($key, $val) = each($tableauIni))
	{
		if($tableauIni[$key]['CONST_AllowAlternatePorts'] == False){$const = "False";}else{$const = "True";}
		echo '<center><table><FORM METHOD=POST ACTION=""><INPUT TYPE = "hidden" NAME = "NewName" VALUE = "'.$key.'" '.$btnN3.'>
		<tr><td>Chemin OSMW: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['cheminAppli'].'" NAME="cheminAppli" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>Destinataire: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['destinataire'].'" NAME="destinataire" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>Autorisation NO LIMIT: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['Autorized'].'" NAME="Autorized" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>Version OSMW: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['VersionOSMW'].'" NAME="VersionOSMW" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>CONST_InternalAddress: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['CONST_InternalAddress'].'" NAME="CONST_InternalAddress" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>CONST_AllowAlternatePorts: </td><td><INPUT TYPE = "text" VALUE = "'.$const.'" NAME="CONST_AllowAlternatePorts" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>CONST_ExternalHostName: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['CONST_ExternalHostName'].'" NAME="CONST_ExternalHostName" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>hostnameBDD : </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['hostnameBDD'].'" NAME="hostnameBDD" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>Database OSMW: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['database'].'" NAME="database" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>userBDD OSMW: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['userBDD'].'" NAME="userBDD" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>passBDD OSMW: </td><td><INPUT TYPE = "password" VALUE = "'.$tableauIni[$key]['passBDD'].'" NAME="passBDD" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>hostnameSSH OSMW: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['hostnameSSH'].'" NAME="hostnameSSH" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>usernameSSH OSMW: </td><td><INPUT TYPE = "text" VALUE = "'.$tableauIni[$key]['usernameSSH'].'" NAME="usernameSSH" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td>passwordSSH OSMW: </td><td><INPUT TYPE = "password" VALUE = "'.$tableauIni[$key]['passwordSSH'].'" NAME="passwordSSH" '.$btnN3.' style="width:300px; height:25px;"></tr>
		<tr><td><td><INPUT TYPE = "submit" VALUE = "Enregistrer" NAME="cmd" '.$btnN3.'></td></tr>	
		</FORM></table></center>';
	}
	echo '</table><hr>';

//******************************************************				
}else{header('Location: index.php');   }

?>