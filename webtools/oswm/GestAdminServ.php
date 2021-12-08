<?php 
include 'variables.php';

if (session_is_registered("authentification") && $_SESSION['privilege']>=3){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Page Administration de tous les Moteurs pour ce serveur.</B>';
	$ligne2 = '<center>********** VEUILLEZ CONSULTER LE LOG ********</center><br>';
	echo '<div class="block" id="clean-gray"><button><CENTER>'.$ligne1.'<br>'.$ligne2.'</CENTER></button></div>';
	echo '<hr>';
	
	//******************************************************
	$btnN1 = "disabled"; $btnN2 = "disabled"; $btnN3 = "disabled";
	if( $_SESSION['privilege']==4){$btnN1="";$btnN2="";$btnN3="";}		//  Niv 4	
	if( $_SESSION['privilege']==3){$btnN1="";$btnN2="";$btnN3="";}		//  Niv 3
	if( $_SESSION['privilege']==2){$btnN1="";$btnN2="";}				//	Niv 2
	if( $_SESSION['privilege']==1){$btnN1="";}							//	Niv 1
	//******************************************************	

		
// *******************************************************************		
// *******************************************************************			

// *******************************************************************	
// ****************  AFFICHAGE PAGE **********************************
// *******************************************************************			
	echo '<center><div class="block" id="pale-blue"><a href="#"><button>*** TESTS / MARCHE / ARRET ***</button></a></div></center><br>';
	echo '<table width=100%><tr>
	<td align=center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Tests Systeme" NAME="cmd" '.$btnN3.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></td>
	<td align=center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="TOUS DEMARRER" NAME="cmd" '.$btnN3.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></td>
	<td align=center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="TOUS ARRETER" NAME="cmd" '.$btnN3.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></td>
	</tr>
	<tr><td colspan=3 align=center>Commande en console opensim: <br>	
	<FORM METHOD=POST ACTION="">
	   <INPUT TYPE="text" VALUE="" NAME="cmd_script" '.$btnN3.'>
		<INPUT TYPE="submit" VALUE="Executer" NAME="cmd" '.$btnN3.'>
		<INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim">
	</FORM></td></tr>
	</table><hr>';
	
	//******  Lecture du INI **********
		 $key = $_SESSION['opensim_select'];
		 $commande = $cmd_etat_OS2;
		 $commande1 = $cmd_Version_mono;
		
		$filename2 = "moteurs.ini";	
		if (file_exists($filename2)) 
			{//echo "Le fichier $filename2 existe.<br>";
			$filename = $filename2 ;
			}else {//echo "Le fichier $filename2 n'existe pas.<br>";
			}
		$tableauIni0 = parse_ini_file($filename, true);
		if($tableauIni0 == FALSE){echo 'prb lecture ini $filename<br>';}
		
	//*****************************************************************
// CONSTRUCTION de la commande pour ENVOI sur la console via  SSH
//*****************************************************************
	if($_POST['cmd'])
	{
	// *** Affichage mode debug ***
	//echo $_POST['cmd'].'<br>';

	// *************** ACTION BOUTON TESTS SYSTEME ***********************
	// *******************************************************************
		if($_POST['cmd'] == 'Executer')
		{ 
			echo $commande=$pre_cmd.$_POST['cmd_script'];
			// Test de connection par serveur *********************************
						if (!function_exists("ssh2_connect")) die("function ssh2_connect doesn't exist");
					// log in at server1.example.com on port 22
					
					if(!($con = ssh2_connect(INI_Conf("Parametre_OSMW","hostnameSSH"), 22))){
						echo "fail: unable to establish connection\n";
					} else 
					{// try to authenticate with username root, password secretpassword
						if(!ssh2_auth_password($con, INI_Conf("Parametre_OSMW","usernameSSH"), INI_Conf("Parametre_OSMW","passwordSSH") )) {
							echo "fail: unable to authenticate\n";
						} else {
							// allright, we're in!
							//echo "Connection au serveur SSH OK<br>";
							//echo "********************************<br>";
							//echo " *******  =>> Liste des Moteurs <<==  ****<br>";
							//echo "********************************<br>";
							// execute a command
							if (!($stream = ssh2_exec($con, $commande ))) {
								echo "fail: unable to execute command\n";
							} else {
								// collect returning data from command
								stream_set_blocking($stream, true);
								$data = "";
								while ($buf = fread($stream,4096)) {
									echo $data .= $buf.'<br>';
								}
								fclose($stream);
							}
						}
					}
					//echo '<br>******************************** <br>Fin Config Serveur Name:'.INI_Conf_Moteur($key,"name").'<br>********************************<br>';
					//echo "<hr><br>";
		}		
		
		if($_POST['cmd'] == 'Tests Systeme') 	// Serie de tests 
		{
		// Commande pour test Serveur lancer 
		echo 'Nombre de serveurs : '.count($tableauIni0).'<br>';
		while (list($key, $val) = each($tableauIni0))
		{
			echo '<hr>Serveur Name:'.INI_Conf_Moteur($key,"name").' - Version:'.INI_Conf_Moteur($key,"version").'<br>';
			echo "********************************<br>";
			
			// *** Lecture Fichier Regions.ini ***
				$tableauIni = parse_ini_file(INI_Conf_Moteur($key,"address")."Regions/Regions.ini", true);
				if($tableauIni == FALSE){echo 'prb lecture ini '.INI_Conf_Moteur($key,"address")."Regions/Regions.ini".'<br>';}else {echo 'Lecture Regions.ini OK<br>';}
				echo "********************************<br>";
				
				// *** Lecture Fichier OpenSimDefaults.ini ***
				$tableauIniOS = parse_ini_file(INI_Conf_Moteur($key,"address")."OpenSimDefaults.ini", true);
				if($tableauIni == FALSE){echo 'prb lecture ini '.INI_Conf_Moteur($key,"address")."OpenSimDefaults.ini".'<br>';}else {echo 'Lecture OpenSimDefaults.ini OK<br>';}
				echo "********************************<br>";
				
				// *** Lecture Fichier RunOpensim.sh ***
				$tableauIniOS = parse_ini_file(INI_Conf_Moteur($key,"address")."RunOpensim.sh", true);
				if($tableauIni == FALSE){echo 'prb lecture RunOpensim.sh '.INI_Conf_Moteur($key,"address")."RunOpensim.sh".'<br>';}else {echo 'Lecture RunOpensim.sh OK<br>';}
				echo "********************************<br>";
				
				// *** Lecture Fichier ScreenSend ***
				$tableauIniOS = parse_ini_file(INI_Conf_Moteur($key,"address")."ScreenSend", true);
				if($tableauIni == FALSE){echo 'prb lecture ScreenSend '.INI_Conf_Moteur($key,"address")."ScreenSend".'<br>';}else {echo 'Lecture ScreenSend OK<br>';}
				echo "********************************<br>";
				
				// Test de connection par serveur *********************************
					if (!function_exists("ssh2_connect")) die("function ssh2_connect doesn't exist");
					// log in at server1.example.com on port 22
					if(!($con = ssh2_connect(INI_Conf("Parametre_OSMW","hostnameSSH"), 22))){
						echo "fail: unable to establish connection\n";
					} else 
					{// try to authenticate with username root, password secretpassword
						if(!ssh2_auth_password($con, INI_Conf("Parametre_OSMW","usernameSSH"), INI_Conf("Parametre_OSMW","passwordSSH") )) {
							echo "fail: unable to authenticate\n";
						} else {
							// allright, we're in!
							echo "Connection au serveur SSH OK<br>";
							echo "********************************************************************************<br>";
							echo " *******  =>> Liste des Moteurs en cours (TOUS) <<==  ****<br>";
							echo "********************************************************************************<br>";
							// execute a command
							$data = $data1 = "";
							if (!($stream = ssh2_exec($con, $commande ))) {
								echo "fail: unable to execute command\n";
							} else {
								// collect returning data from command
								stream_set_blocking($stream, true);
								while ($buf = fread($stream,4096)) {
									 $data .= $buf.'<br>';
								}
								fclose($stream);
							}
							if (!($stream = ssh2_exec($con, $commande1 ))) {
								echo "fail: unable to execute command\n";
							} else {
								// collect returning data from command
								stream_set_blocking($stream, true);
								while ($buf = fread($stream,4096)) {
									 $data1 .= $buf.'<br>';
								}
								fclose($stream);
							}
						}
					}
					//********************* Extraction Info Retour ************
					echo $data;echo '<br>';echo $data1;
					//*********************************************************
					echo '******************************************************************************** <br>Fin Config Serveur Name:'.INI_Conf_Moteur($key,"name").'<br>********************************************************************************<br>';
					echo "<hr><br>";
		}
		
		} 
		
	// *************** ACTION BOUTON TOUS DEMARRER ***********************
	// *******************************************************************	
		if($_POST['cmd'] == 'TOUS DEMARRER')
		{
			echo 'Nombre de serveurs : '.count($tableauIni0).'<br>';
			while (list($key, $val) = each($tableauIni0))
			{
			echo '<hr>Serveur Name:'.INI_Conf_Moteur($key,"name").' - Version:'.INI_Conf_Moteur($key,"version").'<br>';
			echo $commande = "cd ".INI_Conf_Moteur($key,"address").";./RunOpensim.sh"; 


			// Test de connection par serveur *********************************
						if (!function_exists("ssh2_connect")) die("function ssh2_connect doesn't exist");
					// log in at server1.example.com on port 22
					
					if(!($con = ssh2_connect(INI_Conf("Parametre_OSMW","hostnameSSH"), 22))){
						echo "fail: unable to establish connection\n";
					} else 
					{// try to authenticate with username root, password secretpassword
						if(!ssh2_auth_password($con, INI_Conf("Parametre_OSMW","usernameSSH"), INI_Conf("Parametre_OSMW","passwordSSH") )) {
							echo "fail: unable to authenticate\n";
						} else {
							// allright, we're in!
							//echo "Connection au serveur SSH OK<br>";
							//echo "********************************<br>";
							//echo " *******  =>> Liste des Moteurs <<==  ****<br>";
							//echo "********************************<br>";
							// execute a command
							if (!($stream = ssh2_exec($con, $commande ))) {
								echo "fail: unable to execute command\n";
							} else {
								// collect returning data from command
								stream_set_blocking($stream, true);
								$data = "";
								while ($buf = fread($stream,4096)) {
									echo $data .= $buf.'<br>';
								}
								fclose($stream);
							}
						}
					}
					//echo '<br>******************************** <br>Fin Config Serveur Name:'.INI_Conf_Moteur($key,"name").'<br>********************************<br>';
					//echo "<hr><br>";
			}
		}
	
	// *************** ACTION BOUTON TOUS ARRETER ***********************
	// *******************************************************************	
		if($_POST['cmd'] == 'TOUS ARRETER')
		{
			echo 'Nombre de serveurs : '.count($tableauIni0).'<br>';
		while (list($key, $val) = each($tableauIni0))
		{
			echo '<hr>Serveur Name:'.INI_Conf_Moteur($key,"name").' - Version:'.INI_Conf_Moteur($key,"version").'<br>';
			echo $commande = "cd ".INI_Conf_Moteur($key,"address").";./ScreenSend ".INI_Conf_Moteur($key,"name")." shutdown";


			// Test de connection par serveur *********************************
						if (!function_exists("ssh2_connect")) die("function ssh2_connect doesn't exist");
					// log in at server1.example.com on port 22
					
					if(!($con = ssh2_connect(INI_Conf("Parametre_OSMW","hostnameSSH"), 22))){
						echo "fail: unable to establish connection\n";
					} else 
					{// try to authenticate with username root, password secretpassword
						if(!ssh2_auth_password($con, INI_Conf("Parametre_OSMW","usernameSSH"), INI_Conf("Parametre_OSMW","passwordSSH") )) {
							echo "fail: unable to authenticate\n";
						} else {
							// allright, we're in!
							//echo "Connection au serveur SSH OK<br>";
							//echo "********************************<br>";
							//echo " *******  =>> Liste des Moteurs <<==  ****<br>";
							//echo "********************************<br>";
							// execute a command
							if (!($stream = ssh2_exec($con, $commande ))) {
								echo "fail: unable to execute command\n";
							} else {
								// collect returning data from command
								stream_set_blocking($stream, true);
								$data = "";
								while ($buf = fread($stream,4096)) {
									echo $data .= $buf.'<br>';
								}
								fclose($stream);
							}
						}
					}
					//echo '<br>******************************** <br>Fin Config Serveur Name:'.INI_Conf_Moteur($key,"name").'<br>********************************<br>';
					//echo "<hr><br>";
			}
		}
	
//****
	echo "<center><b>Pour chaque action, merci de consulter le LOG avant de relancer une commande.</b></center><br>";
	}
	
// *******************************************************************	


}else{header('Location: index.php');   }
?>