<?php 
include 'variables.php';
include('XMLRPC.php');

if (session_is_registered("authentification"))
{ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Gestion des Sims connectes.</B>';
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

 $channel="d8e93046-dcfc-4a65-81ab-25554eee893f";
  
echo '<FORM METHOD=POST ACTION="">
<TABLE>
<TR>
	<TD>(Listen)IntValue:</TD>
	<TD><INPUT TYPE="text" NAME="intvalue"></TD>
</TR>
<TR>
	<TD>(Commande)StringValue:</TD>
	<TD><INPUT TYPE="text" NAME="stringvalue"></TD>
</TR>
<TR>
	<TD>&nbsp;</TD>
	<TD><INPUT TYPE="submit" NAME="envoi"></TD>
</TR>
</TABLE>
<BR>
<BR>
</FORM>';


//"up,upx2,down,downx2,white1","red1","green1","blue1","steel1","orange1","yellow1","pink1","purple1","sky1","lavander1","OFF"
if($_POST['envoi'])
{
	$myRemoteAdmin = new RemoteAdmin('localhost', 20800, '');
	$type_parameters = array('Channel' => 'string', 'IntValue' => 'int', 'StringValue' => 'string','Channel' => $channel, 'IntValue' => (int)$POST_int ,'StringValue' => $POST_string );
	$myRemoteAdmin->SendCommand('llRemoteData', $type_parameters );	

	 if ($retour === FALSE)
	 {
		  echo 'ERROR';
	 }

}
//******************************************************				
}else{header('Location: index.php');   }
?>
