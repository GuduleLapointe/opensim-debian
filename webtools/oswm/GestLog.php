<meta http-equiv="refresh" content="5; url="#" />
<?php 
include 'variables.php';

if (session_is_registered("authentification")){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Gestion du Fichier Log.</B>';
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
	echo '#   '.$_POST['cmd'].'   #<br>';
	if($_POST['cmd'] == 'Effacer Fichier Log'){$commande = $cmd_Delete_log;}  
	if($_POST['cmd'] == 'Refresh'){$commande = $cmd_etat_OS2;}  
	
//**************************************************************************
// Envoi de la commande par ssh  *******************************************
//**************************************************************************
if($commande <> ''){
	if (!function_exists("ssh2_connect")) die(" function ssh2_connect doesn't exist");
	// log in at server1.example.com on port 22
	if(!($con = ssh2_connect(INI_Conf("Parametre_OSMW","hostnameSSH"), 22))){
		echo " fail: unable to establish connection\n";
	} else 
		{// try to authenticate with username root, password secretpassword
			if(!ssh2_auth_password($con, INI_Conf("Parametre_OSMW","usernameSSH"), INI_Conf("Parametre_OSMW","passwordSSH"))) {
				echo "fail: unable to authenticate\n";
			} else {
				// allright, we're in!
	//echo " ok: logged in...\n";
				// execute a command
				if (!($stream = ssh2_exec($con, $commande ))) {
					echo " fail: unable to execute command\n";
				} else {
					// collect returning data from command
					stream_set_blocking($stream, true);
					$data = "";
					while ($buf = fread($stream,4096)) {
						echo $data .= $buf."\n";
					}
					fclose($stream);
				}
			}
		}
	}
	}
	//******************************************************
	echo '<table width="100%" BORDER=0><tr>';
	echo '<td><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Refresh" NAME="cmd" '.$btnN1.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></td>';
	echo '<td><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Effacer Fichier Log" NAME="cmd" '.$btnN3.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></td>';
	echo '</tr></table>';
	
	$taille_fichier = filesize(INI_Conf_Moteur($_SESSION['opensim_select'],"address").'OpenSim.log');
	if ($taille_fichier >= 1073741824) 
	{		$taille_fichier = round($taille_fichier / 1073741824 * 100) / 100 . " Go";	}
	elseif ($taille_fichier >= 1048576) 
	{		$taille_fichier = round($taille_fichier / 1048576 * 100) / 100 . " Mo";	}
	elseif ($taille_fichier >= 1024) 
	{		$taille_fichier = round($taille_fichier / 1024 * 100) / 100 . " Ko";	}
	else 
	{		$taille_fichier = $taille_fichier . " o";	} 
	echo 'Taille du Fichier Log: '. $taille_fichier.' <BR><hr>';
	
	
	$fcontents = file( INI_Conf_Moteur($_SESSION['opensim_select'],"address").'OpenSim.log');
	$i = sizeof($fcontents)-50;
	while ($fcontents[$i]!="")
		{
		$aff .= $fcontents[$i].'<br>';
		$i++;
		}
	echo '<font t size="1">'.$aff.'</font>';
}else{header('Location: index.php');   }
?>