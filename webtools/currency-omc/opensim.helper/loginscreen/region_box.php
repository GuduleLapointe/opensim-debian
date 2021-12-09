<?php
if(!isset($_GET['regio'])){
  $orderby = "";
} else {
  if($_GET['regio']==""){
    $orderby = " ORDER by regionName ASC";
  }else if($_GET['regio']=="name"){
    $orderby = " ORDER by regionName ASC";
  }else if($_GET['regio']=="x"){
    $orderby = " ORDER by locX ASC";
  }else if($_GET['regio']=="y"){
    $orderby = " ORDER by locY ASC";
  }
}
?>
<table width="300" height="120" border=0 cellPadding=0 cellSpacing=0>
  <tbody>
  <tr>
    <td vAlign=top align=right>
      <table width=100% height="100" border=0 cellPadding=0 cellSpacing=0>
        <tbody>
        <tr>
          <td class=gridbox_tl><img height=5 src="images/login_screens/spacer.gif" width=5 /></td>
          <td class=gridbox_t ><img height=5 src="images/login_screens/spacer.gif" width=5 /></td>
          <td class=gridbox_tr><img height=5 src="images/login_screens/spacer.gif" width=5 /></td>
        </tr>
        <tr>
          <td class=gridbox_l></td>
          <td class=black_content>
            <table cellSpacing=0 cellPadding=1 width="300" border=0>
              <tbody>
              <tr>
                <td width="55%" align=left class=regiontoptext>
                  <a style="cursor:pointer" onclick="document.location.href='?regio=name'"><strong><?php echo $REGION_TTL?></strong></a>
                </td>
                <td width="20%" align=left class=regiontoptext>
                  <div align="center"><a style="cursor:pointer" onclick="document.location.href='?regio=x'"><strong>X</strong></a></div>
                </td>
                <td width="20%" align=left class=regiontoptext>
                  <div align="center"><a style="cursor:pointer" onclick="document.location.href='?regio=y'"><strong>Y</strong></a></div>
                </td>
              </tr>
              </tbody>
            </table>
            <div id=GREX style="MARGIN: 1px 0px 0px"><img height=1 src="images/login_screens/spacer.gif" width=1></div>

            <div style=" border:hidden; color:#ffffff; padding:0px; width:300px; height:160px; overflow:auto; ">
              <?php
              $regions = opensim_get_regions_infos($orderby);
              $w=0;
              foreach($regions as $region) {
                $w++;
                $regionName = $region['regionName'];
                $locX = ((int)$region['locX'])/256;
                $locY = ((int)$region['locY'])/256;
              ?>
            
                <table cellSpacing=0 cellPadding=0 width="100%" border=0>
                  <tbody>
                  <tr <?php if($w==2){$w=0; echo "bgColor=#000000";}else{echo "bgColor=#151515";}?>>
                    <td width="55%" align=left vAlign=top noWrap class=regiontext>
                      <a style="cursor:pointer" onclick="document.location.href='secondlife://<?php echo $regionName?>'">
                        <font color="#cccccc"><u><?php echo $regionName?></u></font>
                      </a>
                    </td>
                    <td width="20%" align=left vAlign=top noWrap class=regiontext><div align="left"><?php echo "($locX)"?></div></td>
                    <td width="20%" align=left vAlign=top noWrap class=regiontext><div align="left"><?php echo "($locY)"?></div></td>
                  </tr>
                  </tbody>
                </table>
              <?php
              }
              ?> 
            </div>

          </td>
          <td class=gridbox_r></td>
        </tr>
        <tr>
          <td class=gridbox_bl><img height=5 src="images/login_screens/spacer.gif" width=5 /></td>
          <td class=gridbox_b ><span class="gridbox_br"><img height="5" src="images/login_screens/spacer.gif" width="5" /></span></td>
          <td class=gridbox_br><img height=5 src="images/login_screens/spacer.gif" width=5 /></td>
        </tr>
        </tbody>
      </table>
                    
    </td>
  </tr>
  </tbody>
</table>
