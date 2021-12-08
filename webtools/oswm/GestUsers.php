<?php 
include 'variables.php';

if (session_is_registered("authentification") && $_SESSION['privilege']>=3){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Gestion des Utilisateurs de OpenSim Manager Web.</B>';
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
	//******************************************************
	// On charge le fichier INI
	$filename2 = "/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."users.ini";

	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	//*****************************************************************
		if($_POST['cmd'] == 'Ajouter')
		{
			echo '<FORM METHOD=POST ACTION=""><table width="100%" BORDER=0><TR>
				<td>Nom de l\'utilisateur: <INPUT TYPE = "text" NAME = "NewName" VALUE = "Prénom Nom" '.$btnN3.'></td>
				<td>Mot de passe: <INPUT TYPE = "text" NAME = "username_pass" VALUE = "" '.$btnN3.'></td>
					<td><select name="username_priv"><option value="1">Invité</option><option value="2">Gestionnaire</option><option value="3" >Administrateur</option></select></td>
				<tr><td align=center><INPUT TYPE="submit" VALUE = "Enregistrer" NAME="cmd" '.$btnN3.'></td></tr>
			</table><hr></FORM>';
		} 
	//*****************************************************************	
		if($_POST['cmd'] == 'Enregistrer')
		{	
			// AJOUTER chaque valeur
			$tableauIni[$_POST['NewName']]['pass'] = $_POST['username_pass'];
			$tableauIni[$_POST['NewName']]['privilege'] = $_POST['username_priv'];
			
			// Enregistrement du nouveau fichier 
			$fp = fopen ("/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."users.ini", "w");  
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					//	echo "[".$key."]\r\n";
					fputs($fp,"pass = ".$tableauIni[$key]['pass']."\r\n");
					//	echo "pass = ".$tableauIni[$key]['pass']."\r\n";
					fputs($fp,"privilege = ".$tableauIni[$key]['privilege']."\r\n");
					//	echo "privilege = ".$tableauIni[$key]['privilege']."\r\n";
				}
			fclose ($fp);  
		} 
	//*****************************************************************
		if($_POST['cmd'] == 'Modifier')
		{
			if($_POST['oldName'] == $_POST['NewName'])
			{
		//	echo $_POST['NewName'].' == '.$_POST['oldName'].'<br>';
				// AJOUTER chaque valeur
				$tableauIni[$_POST['NewName']]['pass'] = $_POST['username_pass'];
				$tableauIni[$_POST['NewName']]['privilege'] = $_POST['username_priv'];
			}
			if($_POST['oldName'] <> $_POST['NewName'])
			{
		//	echo $_POST['NewName'].' <> '.$_POST['oldName'];
				// MODIFIER chaque valeur pour la region sellectionner ==> AJOUT Nouveau
				$tableauIni[$_POST['NewName']]['pass'] = $_POST['username_pass'];
				$tableauIni[$_POST['NewName']]['privilege'] = $_POST['username_priv'];
				// MODIFIER chaque valeur pour la region sellectionner ==> SUPPRESSION  Ancien
				unset($tableauIni[$_POST['oldName']]['pass']);
				unset($tableauIni[$_POST['oldName']]['privilege']);
				unset($tableauIni[$_POST['oldName']]);
			}
			// Enregistrement du nouveau fichier 
			$fp = fopen ("/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."users.ini", "w");  
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					fputs($fp,"pass = ".$tableauIni[$key]['pass']."\r\n");
					fputs($fp,"privilege = ".$tableauIni[$key]['privilege']."\r\n");
				}
			fclose ($fp);  
		} 
	//*****************************************************************
		if($_POST['cmd'] == 'Supprimer')
		{			
			// MODIFIER chaque valeur pour la region sellectionner ==> SUPPRESSION  Ancien
			unset($tableauIni[$_POST['NewName']]['pass']);
			unset($tableauIni[$_POST['NewName']]['privilege']);
			unset($tableauIni[$_POST['NewName']]);
			// Enregistrement du nouveau fichier 
			$fp = fopen ("/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."users.ini", "w");   
			while (list($key, $val) = each($tableauIni))
				{
					fputs($fp,"[".$key."]\r\n");
					fputs($fp,"pass = ".$tableauIni[$key]['pass']."\r\n");
					fputs($fp,"privilege = ".$tableauIni[$key]['privilege']."\r\n");
				}
			fclose ($fp);  
		} 
}
//******************************************************

	// *** Lecture Fichier Regions.ini ***
	$filename2 = "/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."users.ini";	
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	$i=0;
	echo '<u>Nb Utilisateurs :</u> '.count($tableauIni).'</b><BR><br>';
	
	echo '<FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Ajouter" NAME="cmd" '.$btn.'> Permet d\'ajouter un nouvel Utilisateur.</FORM></center>';
	echo '<hr><table width="100%" BORDER=0><TR>';
	while (list($key, $val) = each($tableauIni))
	{
			$privilegetxt1 = $privilegetxt2 = $privilegetxt3 = 0;
			$privilege = $tableauIni[$key]['privilege'];
			 $oldbtnN3 =  $btnN3;
			switch ($privilege) {
				case 1: $privilegetxt1 = "selected";break;
				case 2: $privilegetxt2 = "selected";break;
				case 3: $privilegetxt3 = "selected";break;
				case 4: 
				if($_SESSION['privilege']==4)
				{
					$privilegetxt4 = "<option value='4' selected>Super Administrateur</option>";$block="";$btnN3 = "";
						break;
				}else{
					$privilegetxt4 = "<option value='4' selected>Super Administrateur</option>";$block="disabled";$btnN3 = "disabled";
						break;
				}
			}
		echo '<tr>
		<FORM METHOD=POST ACTION=""><INPUT TYPE = "hidden" NAME = "oldName" VALUE="'.$key.'" >
		<tr><td><b><u>'.$key.'</u></b></td><td>Mot de passe</td><td>Privilege</td></tr>
		<tr>
			<td><INPUT TYPE = "text" NAME = "NewName" VALUE = "'.$key.'" '.$btnN3.'></td>
			<td><INPUT TYPE = "password" NAME = "username_pass" VALUE = "'.$tableauIni[$key]['pass'].'" '.$btnN3.'></td>
			<td><select name="username_priv" '.$block.'><option value="1" '.$privilegetxt1.' >Invité</option><option value="2" '.$privilegetxt2.'>Gestionnaire</option><option value="3" '.$privilegetxt3.'>Administrateur</option>'.$privilegetxt4.'</select></td>
			<td><INPUT TYPE = "submit" VALUE = "Modifier" NAME="cmd" '.$btnN3.'></td>
			<td><INPUT TYPE = "submit" VALUE = "Supprimer" NAME="cmd" '.$btnN3.'></td>
		</tr>
		</FORM>			
		</tr>';
		 $btnN3 =  $oldbtnN3;$privilegetxt4="";$block="";
	}
	echo '</table><hr>';

//******************************************************				
}else{header('Location: index.php');   }

?>