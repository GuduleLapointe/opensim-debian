<?php 
if (session_is_registered("authentification"))
{ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Aide sur OpenSim Manager Web.</B>';
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

	if (!$fp = fopen("README.TXT","r")) {
echo "Echec de l'ouverture du fichier";
exit;
}
else {
	while(!feof($fp)) {
	// On récupère une ligne
		$Ligne = fgets($fp,255);
	// On affiche la ligne
		echo $Ligne.'<br>';
	// On stocke l'ensemble des lignes dans une variable
		$Fichier .= $Ligne;
	}
	fclose($fp); // On ferme le fichier
}
	
//******************************************************				
}else{header('Location: index.php');   }
?>