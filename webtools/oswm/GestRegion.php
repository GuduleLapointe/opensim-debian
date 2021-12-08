<?php 
include 'variables.php';

if (session_is_registered("authentification") && $_SESSION['privilege']>=3){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Configuration des Sims connectees.</B>';
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
//******************************************************
// CONSTRUCTION de la commande pour ENVOI sur la console via  SSH
//******************************************************
if($_POST['cmd'])
{
	// *** Affichage mode debug ***
	echo $_POST['cmd'];echo '<BR>';
	echo '<BR><HR>';
	// On charge le fichier INI
	
	
	// *** Lecture Fichier Regions.ini ***
	$IP_Public = INI_Conf("Parametre_OSMW","hostnameSSH");
	$filename = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/Regions.ini";	// *** V 0.7.1 ***
	
	if (file_exists($filename)) 
		{//echo "Le fichier $filename1 existe.<br>";
		}else {echo "Le fichier $filename n'existe pas.<br>";}

	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini $filename<br>';}
	
		if($_POST['cmd'] == 'Ajouter')
		{
			echo '<FORM METHOD=POST ACTION=""><table width="100%" BORDER=0><TR>
				<td>Nom de la region: <INPUT TYPE = "text" NAME = "NewName" VALUE = "Nom region" '.$btnN3.'></td>
				<td>UUID (auto): <INPUT TYPE = "text" NAME = "RegionUUID" VALUE = "'.GenUUID().'" '.$btnN3.'></td>
				<td>Location: <INPUT TYPE = "text" NAME = "Location" VALUE = "ex: 6989,6789" '.$btnN3.'></td>
				<td>Port Interne; <INPUT TYPE = "text" NAME = "InternalPort" VALUE = "ex: 9060" '.$btnN3.'></td>
				<tr><td align=center><INPUT TYPE="submit" VALUE = "Enregistrer" NAME="cmd" '.$btnN3.'></td></tr>
			</table><hr></FORM>';
		} 
		if($_POST['cmd'] == 'Enregistrer')
		{	
			// AJOUTER chaque valeur
			$tableauIni[$_POST['NewName']]['RegionUUID'] = $_POST['RegionUUID'];
			$tableauIni[$_POST['NewName']]['Location'] = $_POST['Location'];
			$tableauIni[$_POST['NewName']]['InternalAddress'] = $IP_Public;
			$tableauIni[$_POST['NewName']]['InternalPort'] = $_POST['InternalPort'];
			$tableauIni[$_POST['NewName']]['ExternalHostName'] = $IP_Public;
			
			// Enregistrement du nouveau fichier 
			$fp = fopen (INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/RegionTemp.ini", "w");  
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					fputs($fp,"RegionUUID = ".$tableauIni[$key]['RegionUUID']."\r\n");
					fputs($fp,"Location = ".$tableauIni[$key]['Location']."\r\n");
					fputs($fp,"InternalAddress = ".$IP_Public."\r\n");
					fputs($fp,"InternalPort = ".$tableauIni[$key]['InternalPort']."\r\n");
					fputs($fp,"AllowAlternatePorts = False\r\n");
					fputs($fp,"ExternalHostName = ".$IP_Public."\r\n");
				}
			fclose ($fp);  
			 exec_command("chmod -R 777 ".INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/");
			// Suppression de l'original
			 unlink($filename); 
			 // Renommer le temp en original
			 rename(INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/RegionTemp.ini",$filename); 
		} 
		if($_POST['cmd'] == 'Modifier')
		{

			if($_POST['name_sim'] == $_POST['NewName'])
			{
			//echo $_POST['NewName'].' == '.$_POST['name_sim'].'<br>';
				// AJOUTER chaque valeur
				$tableauIni[$_POST['NewName']]['RegionUUID'] = $_POST['RegionUUID'];
				$tableauIni[$_POST['NewName']]['Location'] = $_POST['Location'];
				$tableauIni[$_POST['NewName']]['InternalAddress'] = $IP_Public;
				$tableauIni[$_POST['NewName']]['InternalPort'] = $_POST['InternalPort'];
				$tableauIni[$_POST['NewName']]['ExternalHostName'] = $IP_Public;
			}
			if($_POST['name_sim'] <> $_POST['NewName'])
			{
			//echo $_POST['NewName'].' <> '.$_POST['name_sim'];
				// MODIFIER chaque valeur pour la region sellectionner ==> AJOUT Nouveau
				$tableauIni[$_POST['NewName']]['RegionUUID'] = $_POST['RegionUUID'];
				$tableauIni[$_POST['NewName']]['Location'] = $_POST['Location'];
				$tableauIni[$_POST['NewName']]['InternalAddress'] = $IP_Public;
				$tableauIni[$_POST['NewName']]['InternalPort'] = $_POST['InternalPort'];
				$tableauIni[$_POST['NewName']]['ExternalHostName'] = $IP_Public;
				// MODIFIER chaque valeur pour la region sellectionner ==> SUPPRESSION  Ancien
				unset($tableauIni[$_POST['name_sim']]['RegionUUID']);
				unset($tableauIni[$_POST['name_sim']]['Location']);
				unset($tableauIni[$_POST['name_sim']]['InternalAddress']);
				unset($tableauIni[$_POST['name_sim']]['InternalPort']);
				unset($tableauIni[$_POST['name_sim']]['AllowAlternatePorts'] );
				unset($tableauIni[$_POST['name_sim']]['ExternalHostName']);
				unset($tableauIni[$_POST['name_sim']]);
			}
			// Enregistrement du nouveau fichier 
			$fp = fopen (INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/RegionTemp.ini", "w");  
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					fputs($fp,"RegionUUID = ".$tableauIni[$key]['RegionUUID']."\r\n");
					fputs($fp,"Location = ".$tableauIni[$key]['Location']."\r\n");
					fputs($fp,"InternalAddress = ".$IP_Public."\r\n");
					fputs($fp,"InternalPort = ".$tableauIni[$key]['InternalPort']."\r\n");
					fputs($fp,"AllowAlternatePorts = False\r\n");
					fputs($fp,"ExternalHostName = ".$IP_Public."\r\n");
				}
			fclose ($fp);  
			// Suppression de l'original
			unlink($filename); 
			 // Renommer le temp en original
			rename(INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/RegionTemp.ini",$filename); 
			 exec_command("chmod -R 777 ".INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/");
		} 
		if($_POST['cmd'] == 'Supprimer')
		{			

			// MODIFIER chaque valeur pour la region sellectionner ==> SUPPRESSION  Ancien
			unset($tableauIni[$_POST['name_sim']]['RegionUUID']);
			unset($tableauIni[$_POST['name_sim']]['Location']);
			unset($tableauIni[$_POST['name_sim']]['InternalAddress']);
			unset($tableauIni[$_POST['name_sim']]['InternalPort']);
			unset($tableauIni[$_POST['name_sim']]['AllowAlternatePorts'] );
			unset($tableauIni[$_POST['name_sim']]['ExternalHostName']);
			unset($tableauIni[$_POST['name_sim']]);
			
			// Enregistrement du nouveau fichier 
			$fp = fopen (INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/RegionTemp.ini", "w");  
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					fputs($fp,"RegionUUID = ".$tableauIni[$key]['RegionUUID']."\r\n");
					fputs($fp,"Location = ".$tableauIni[$key]['Location']."\r\n");
					fputs($fp,"InternalAddress = ".$IP_Public."\r\n");
					fputs($fp,"InternalPort = ".$tableauIni[$key]['InternalPort']."\r\n");
					fputs($fp,"AllowAlternatePorts = False\r\n");
					fputs($fp,"ExternalHostName = ".$IP_Public."\r\n");
				}
			fclose ($fp);  
			// Suppression de l'original
			 unlink($filename); 
			 // Renommer le temp en original
			  rename(INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/RegionTemp.ini",$filename); 
			  exec_command("chmod -R 777 ".INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/");
		} 
}
//******************************************************
//  Affichage page principale
//******************************************************
	
	// *** Lecture Fichier Regions.ini ***
	$filename2 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/Regions.ini";	// *** V 0.7.1 ***

	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	$i=0;
	echo '<b><u>Nb regions Max:</u> 4  |  '.'<u>Nb regions connectes:</u> '.count($tableauIni).'</b><BR><br>';
	if(count($tableauIni) >= 4)
		{$btn = 'disabled';}else{$btn=$btnN3;}
	if(INI_Conf("Parametre_OSMW","Autorized") == '1' )
		{$btn = '';}
	echo '<FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Ajouter" NAME="cmd" '.$btn.'> Permet d\'ajouter une nouvelle region au moteur Opensim.</FORM></center>';
	
//******************************************************	
	echo '<hr><table width="100%" BORDER=0><TR>';
	while (list($key, $val) = each($tableauIni))
	{
		//echo $key.$tableauIni[$key]['RegionUUID'].$tableauIni[$key]['Location'].$tableauIni[$key]['InternalPort'].'<br>';
		echo '<tr>
		<FORM METHOD=POST ACTION=""><INPUT TYPE = "hidden" NAME = "name_sim" VALUE="'.$key.'" >
		<tr><td colspan=6><b><u>'.$key.'</u></b></td></tr>
		<tr><td>Nom de la region</td><td>Coordonnes</td><td>Port Http region</td><td>IP Public</td><td>Region UUID</td></tr>
		<tr><td><INPUT TYPE = "text" NAME = "NewName" VALUE = "'.$key.'" '.$btnN3.'></td>
			<td><INPUT TYPE = "text" NAME = "Location" VALUE = "'.$tableauIni[$key]['Location'].'" '.$btnN3.'></td>
			<td><INPUT TYPE = "text" NAME = "InternalPort" VALUE = "'.$tableauIni[$key]['InternalPort'].'" '.$btnN3.'></td>
			<td>'.$tableauIni[$key]['ExternalHostName'].'</td>
			<td><INPUT TYPE = "text" NAME = "RegionUUID" VALUE = "'.$tableauIni[$key]['RegionUUID'].'" '.$btnN3.'></td>
		</tr>	
			<tr><td colspan=6 align=center>
				<INPUT TYPE = "submit" VALUE = "Modifier" NAME="cmd" '.$btnN3.'>
				<INPUT TYPE = "submit" VALUE = "Supprimer" NAME="cmd" '.$btnN3.'>
			</td></tr>
		</FORM>			
		</tr>';
	}
	echo '</table><hr>';
	
//******************************************************	

}else{header('Location: index.php');   }

function exec_command($commande){
	$output = shell_exec($commande);
	return $output;
}

function GenUUID() {
	return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
	mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
	mt_rand( 0, 0x0fff ) | 0x4000,
	mt_rand( 0, 0x3fff ) | 0x8000,
	mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
}
?>