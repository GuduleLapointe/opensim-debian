<?php 
include 'variables.php';

if (session_is_registered("authentification") && $_SESSION['privilege']>=3){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Configuration des Moteurs Opensim connectes.</B>';
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
	
	
	// *** Lecture Fichier .ini ***
	$filename = "moteurs.ini";	
	if (file_exists($filename)) 
		{//echo "Le fichier $filename1 existe.<br>";
		}else {echo "Le fichier $filename n'existe pas.<br>";}

	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini $filename<br>';}
	
		if($_POST['cmd'] == 'Ajouter')
		{
			$compteur = count($tableauIni) + 1;
		
			echo '<FORM METHOD=POST ACTION=""><table width="100%" BORDER=0><TR>
				<td>Num Moteur: <INPUT TYPE = "text" NAME = "NewName" VALUE = "'.$compteur.'" '.$btnN3.' style="width:300px; height:25px;"></td>
				<td>Libelle Moteur: <INPUT TYPE = "text" NAME = "version" VALUE = "" '.$btnN3.' style="width:500px; height:25px;"></td>
				<td>Emplacement physique: <INPUT TYPE = "text" NAME = "address" VALUE = "/home/user/moteur2/" '.$btnN3.' style="width:300px; height:25px;"></td>
				<td>Nom BDD; <INPUT TYPE = "text" NAME = "DB_OS" VALUE = "OpensimDB" '.$btnN3.'></td>
				<tr><td align=center><INPUT TYPE="submit" VALUE = "Enregistrer" NAME="cmd" '.$btnN3.'></td></tr>
			</table><hr></FORM>';
		} 
		if($_POST['cmd'] == 'Enregistrer')
		{	
			// AJOUTER chaque valeur
			$tableauIni[$_POST['NewName']]['name'] = $_POST['NewName'];
			$tableauIni[$_POST['NewName']]['version'] = $_POST['version'];
			$tableauIni[$_POST['NewName']]['address'] = $_POST['address'];
			$tableauIni[$_POST['NewName']]['DB_OS'] = $_POST['DB_OS'];
			
			// Enregistrement du nouveau fichier 
			$fp = fopen ("moteursTemp.ini", "w");  
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					fputs($fp,"name = ".$tableauIni[$key]['name']."\r\n");
					fputs($fp,"version = ".$tableauIni[$key]['version']."\r\n");
					fputs($fp,"address = ".$tableauIni[$key]['address']."\r\n");
					fputs($fp,"DB_OS = ".$tableauIni[$key]['DB_OS']."\r\n");
				}
			fclose ($fp);  
			
			// Suppression de l'original
			 unlink($filename); 
			 // Renommer le temp en original
			 rename("moteursTemp.ini",$filename); 
			 exec_command("chmod 777 /var/www".INI_Conf("Parametre_OSMW","cheminAppli")."moteurs.ini");
		} 
		if($_POST['cmd'] == 'Modifier')
		{

			if($_POST['name_sim'] == $_POST['NewName'])
			{
			//echo $_POST['NewName'].' == '.$_POST['name_sim'].'<br>';
				// AJOUTER chaque valeur
				$tableauIni[$_POST['NewName']]['name'] = $_POST['NewName'];
				$tableauIni[$_POST['NewName']]['version'] = $_POST['version'];
				$tableauIni[$_POST['NewName']]['address'] = $_POST['address'];
				$tableauIni[$_POST['NewName']]['DB_OS'] = $_POST['DB_OS'];
			}
			if($_POST['name_sim'] <> $_POST['NewName'])
			{
			//echo $_POST['NewName'].' <> '.$_POST['name_sim'];
				// MODIFIER chaque valeur pour la region sellectionner ==> AJOUT Nouveau
				$tableauIni[$_POST['NewName']]['name'] = $_POST['NewName'];
				$tableauIni[$_POST['NewName']]['version'] = $_POST['version'];
				$tableauIni[$_POST['NewName']]['address'] = $_POST['address'];
				$tableauIni[$_POST['NewName']]['DB_OS'] = $_POST['DB_OS'];
				// MODIFIER chaque valeur pour la region sellectionner ==> SUPPRESSION  Ancien
				unset($tableauIni[$_POST['name_sim']]['name']);
				unset($tableauIni[$_POST['name_sim']]['version']);
				unset($tableauIni[$_POST['name_sim']]['address']);
				unset($tableauIni[$_POST['name_sim']]['DB_OS']);
				unset($tableauIni[$_POST['name_sim']]);
			}
			// Enregistrement du nouveau fichier 
			$fp = fopen ("moteursTemp.ini", "w");  
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					fputs($fp,"name = ".$tableauIni[$key]['name']."\r\n");
					fputs($fp,"version = ".$tableauIni[$key]['version']."\r\n");
					fputs($fp,"address = ".$tableauIni[$key]['address']."\r\n");
					fputs($fp,"DB_OS = ".$tableauIni[$key]['DB_OS']."\r\n");
				}
			fclose ($fp);  
			// Suppression de l'original
			 unlink($filename); 
			 // Renommer le temp en original
			 rename("moteursTemp.ini",$filename); 
			 exec_command("chmod 777 /var/www".INI_Conf("Parametre_OSMW","cheminAppli")."moteurs.ini");
		} 
		if($_POST['cmd'] == 'Supprimer')
		{			

			// MODIFIER chaque valeur pour la region sellectionner ==> SUPPRESSION  Ancien
			unset($tableauIni[$_POST['name_sim']]['name']);
			unset($tableauIni[$_POST['name_sim']]['version']);
			unset($tableauIni[$_POST['name_sim']]['address']);
			unset($tableauIni[$_POST['name_sim']]['DB_OS']);
			unset($tableauIni[$_POST['name_sim']]);
			
			// Enregistrement du nouveau fichier 
			$fp = fopen ("moteursTemp.ini", "w");  
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					fputs($fp,"name = ".$tableauIni[$key]['name']."\r\n");
					fputs($fp,"version = ".$tableauIni[$key]['version']."\r\n");
					fputs($fp,"address = ".$tableauIni[$key]['address']."\r\n");
					fputs($fp,"DB_OS = ".$tableauIni[$key]['DB_OS']."\r\n");
				}
			fclose ($fp);  
			// Suppression de l'original
			 unlink($filename); 
			 // Renommer le temp en original
			 rename("moteursTemp.ini",$filename); 
			 exec_command("chmod 777 /var/www".INI_Conf("Parametre_OSMW","cheminAppli")."moteurs.ini");
		} 
}
//******************************************************
//  Affichage page principale
//******************************************************
	
	// *** Lecture Fichier Regions.ini ***
	$filename2 = "moteurs.ini";	

	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	$i=0;
	echo '<b><u>Nb Moteurs Max:</u> 4  |  '.'<u>Nb Moteurs Opensim connectes:</u> '.count($tableauIni).'</b><BR><br>';
	if(count($tableauIni) >= 4)
		{$btn = 'disabled';}else{$btn=$btnN3;}
	if(INI_Conf("Parametre_OSMW","Autorized") == '1' )
		{$btn = '';}
	echo '<FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Ajouter" NAME="cmd" '.$btn.'> Permet d\'ajouter une nouvelle region au moteur Opensim.</FORM></center>';
	
//******************************************************	
	echo '<hr><table width="100%" BORDER=0><TR>';
	while (list($key, $val) = each($tableauIni))
	{
		echo '<tr>
		<FORM METHOD=POST ACTION=""><INPUT TYPE = "hidden" NAME = "name_sim" VALUE="'.$key.'" >
		<tr><td colspan=6><b><u>'.$key.'</u></b></td></tr>
		<tr><td>Numero du Moteur</td><td>Libelle</td><td>Address</td><td>BDD Opensim</td></tr>
		<tr><td><INPUT TYPE = "text" NAME = "NewName" VALUE = "'.$key.'" '.$btnN3.'></td>
			<td><INPUT TYPE = "text" NAME = "version" VALUE = "'.$tableauIni[$key]['version'].'" '.$btnN3.'></td>
			<td><INPUT TYPE = "text" NAME = "address" VALUE = "'.$tableauIni[$key]['address'].'" '.$btnN3.'></td>
			<td><INPUT TYPE = "text" NAME = "DB_OS" VALUE = "'.$tableauIni[$key]['DB_OS'].'" '.$btnN3.'></td>
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


?>