<?php 
include 'variables.php';

if (session_is_registered("authentification") && $_SESSION['privilege']>=3){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Configuration des INI d\'Opensim.</B>';
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
//******************************************************
//  Affichage page principale
//******************************************************
	
// *** Lecture Fichier ini ***
	$filename1 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."OpenSim.ini";				
	$filename2 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."OpenSimDefaults.ini";		
	if (file_exists($filename1)) 
		{echo "Le fichier OpenSim.ini existe.<br>";
		}else {echo "<B>Le fichier OpenSim.ini n'existe pas.</B><br>";}
	if (file_exists($filename2)) 
		{echo "Le fichier OpenSimDefaults.ini existe.<br>";
		}else {echo "<B>Le fichier OpenSimDefaults.ini n'existe pas.</B><br>";}
		
// Configuration du fichier OpenSimDefaults.ini *******************************************
echo '<hr><center><i><u>Les pricipaux parametres de OpenSimDefaults.ini</u></i></center>';
	

if (!$fp2 = fopen($filename2,"r")) 
	{echo "Echec de l'ouverture du fichier OpenSimDefaults.ini";}		
	$tabfich2=file($filename2); 
	echo '<center><table>';
	for( $i = 0 ; $i < count($tabfich2) ; $i++ )
		{//	$contenue2 = $contenue2.'<br>'.$tabfich2[$i];
		$retValeur = ExtractValeur($tabfich2[$i]);
		//echo $retValeur[0].' = '.$retValeur[1].'<br>';
		if($retValeur[0] == "CLES"){echo '<tr><td colspan=2><hr><b><u>'.$retValeur[1].'</u></b></td></tr>';}
		else{echo '<tr><td>'.$retValeur[0].'</td><td><INPUT TYPE = "text" VALUE = "'.$retValeur[1].'" NAME="'.$retValeur[0].'" '.$btnN3.' style="width:400px; height:25px;"></td></tr>';}
		}
	fclose($fp2);
	echo '</table></center>';
//	echo '<font t size="1">'.$contenue2.'</font>';

// Configuration du fichier OpenSim.ini *******************************************

/*
echo '<hr><center><i><u>Les pricipaux parametres de OpenSim.ini</u></i></center>';
		
if (!$fp1 = fopen($filename1,"r")) 
	{echo "Echec de l'ouverture du fichier OpenSim.ini";}		
	$tabfich1=file($filename1); 
	for( $i = 0 ; $i < count($tabfich1) ; $i++ )
		{$contenue1 = $contenue1.'<br>'.$tabfich1[$i];}
	fclose($fp1);
	echo '<font t size="1">'.$contenue1.'</font>';	
	
*/
//******************************************************	
}else{header('Location: index.php');   }
?>