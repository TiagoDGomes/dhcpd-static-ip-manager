<?php
/**
 * dhcpd-static-ip-manager 
 * @author Tiago Donizetti Gomes (https://github.com/TiagoDGomes/dhcpd-static-ip-manager)
 *  
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
 
$USERNAME = 'user';
$PASSWORD = 'p455w0rd';

$ETHERS_FILE = '/etc/ethers';
$HOSTS_FILE = '/etc/hosts';
$DHCPD_HEADER_CONF = '/etc/dhcp3/dhcpd.conf.header';
$DHCPD_CONF = '/etc/dhcp3/dhcpd.conf';

$URL_SERVER = "http://10.0.0.1/rede";
$DHCP_IP_START = "10.0.0.2";
$DHCP_IP_END = "10.0.0.254"; 
$PREFFIX_UNKNOWN = 'host';
$DOMAIN = 'example.com';

$CMD_RESTART_DHCP = 'sudo -u root -S service dhcp3-server restart < /var/local/chp';
$CMD_FLUSH_ARP = "sudo -u root -S arp -f $ETHERS_FILE < /var/local/chp";


if ($_SERVER['PHP_AUTH_USER'] != $USERNAME || $_SERVER['PHP_AUTH_PW'] != $PASSWORD) {
    header('WWW-Authenticate: Basic realm="Network"');
    header('HTTP/1.0 401 Unauthorized');
    echo '401 Unauthorized';
    exit;
}

$iss = explode( ".", $DHCP_IP_START);
$ies = explode( "." , $DHCP_IP_END);
for ($a = $iss[0]; $a <= $ies[0]; $a++){
  for ($b = $iss[1]; $b <= $ies[1]; $b++){
    for ($c = $iss[2]; $c <= $ies[2]; $c++){
      for ($d = $iss[3]; $d <= $ies[3]; $d++){
          $machine["$a.$b.$c.$d"]['name'] = "{$PREFFIX_UNKNOWN}_${a}_${b}_${c}_${d}";
          $machine["$a.$b.$c.$d"]['reg'] = false;
      }
    }
  }
}


$hosts  = @file($HOSTS_FILE);
$ethers = @file($ETHERS_FILE);
@natsort($hosts);
@natsort($ethers);
if (isset($_POST['dhcpheader'])){
        $dhcph = $_POST['dhcpheader'];
        $f = fopen($DHCPD_HEADER_CONF, 'w');
        fwrite($f, $dhcph);
        fclose($f);
        unset($f);
} else {
        $dhcph  = @file_get_contents($DHCPD_HEADER_CONF);
}
// Search 'hosts' file

if (is_array($hosts)){
    foreach($hosts as $h){
            $h_name = trim(strstr($h, " "));
            if ($h_name == ''){
                    $h_name = trim(strstr($h, "\t"));
            }
            $h_ip = trim(str_replace($h_name,"",$h));
            if (@$_GET['f']!= 'delete' || $h_ip != @$_GET['h_ip']){
                    if (strpos($h_ip,"#")===FALSE && $h_ip != ''){
                            $machine[$h_ip]['name'] = $h_name;
                            $machine[$h_ip]['reg'] = (substr($h_name,0,strlen($PREFFIX_UNKNOWN)) != $PREFFIX_UNKNOWN);
    
                    }
            }
    }
}

// Search 'ethers' file
if (is_array($ethers)){
    foreach($ethers as $e){
            $e_name = trim(strstr($e, " "));
            if ($e_name == ''){
                    $e_name = trim(strstr($e, "\t"));
            }
            $e_ip = trim(str_replace($e_name,"",$e));
            if (@$_GET['f']!='delete' || $e_ip != @$_GET['h_ip']){
                    if (strpos($e_ip,"#")===FALSE && $e_ip != ''){
                            $machine[$e_ip]['mac'] = $e_name;
                            $machine[$e_ip]['reg'] = true;
    
                    }
            }
    }
}

// save file
if (isset($_GET['f'])||isset($_POST['f'])){
        $f = fopen($HOSTS_FILE, 'w');
        $e = fopen($ETHERS_FILE, 'w');
        $d = fopen($DHCPD_CONF, 'w');
        $msg = "#\n#\n#\n# ATTENTION! Don't edit this file. \n# Go to: $URL_SERVER\n#\n#\n\n#\n";

        fwrite($d, $msg . $dhcph);
        $machine[$_GET['h_ip']]['name'] = $_GET['h_name'];
        $machine[$_GET['h_ip']]['mac'] = $_GET['h_mac'];
        $machine[$_GET['h_ip']]['reg'] = ($_GET['f'] != 'delete');

        $i = 0;
        foreach($machine as $ip=> $m){
                $i++;

                $name = str_replace('.','_',$m['name']);
                $name = str_replace('-',"_",$name);
                $name = str_replace(' ',"_",$name);

                if ($m['name']!=null){
                        fwrite($f, trim($ip) . "\t{$m['name']}\n");
                }
                if (@$m['mac']!=null){
                        $mac = strtoupper(str_replace("-",":",$m['mac']));
                        fwrite($e, trim($ip) . "\t$mac\n");
                        if ($mac != '00:00:00:00:00:00') {
                                fwrite($d, "host HOST_$i {hardware ethernet $mac;option host-name \"$name.$DOMAIN\";fixed-address $ip;}\n");
                        }
                }
        }
        fclose($f);
        fclose($e);

        fwrite($d, "$msg" );
        fclose($d);
        exec ($CMD_RESTART_DHCP);
        exit(header ("Location: ?#{$_GET['h_ip']}"));

}
$log = '';
if (@$_GET['dhcp']=='restart'){

        $log  = shell_exec ($CMD_RESTART_DHCP);
        $log .= shell_exec ($CMD_FLUSH_ARP);
//      exit(header ("Location: ?"));
}

?>
<html>
  <head>
      <title>Network</title>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
      <style>
              button,textarea, body, table,input{font-family:Tahoma, Arial, helvetica, sans-serif; font-size:8pt}
              textarea{font-family: Consolas, monospace}
              table{border-collapse: collapse; border: 1px solid silver;margin: 0 auto}
              .novo td{background-color: #00ffff}
              .ip {text-align: center}
              .unreg{background-color: #77ff99}
              .reg{background-color: #ffaaaa}
      </style>
  </head>
  <body>
    <table style="margin: 0 auto">
      <tr><th colspan="5">Network</th></tr>
      <tr><td></td><td><button onclick="window.location='?dhcp=restart'">Restart DHCP</button></td><td></td><td></td><td></td></tr>
      <tr><td colspan="5"><pre><?= $log ?></pre></td></tr>
      <tr>
              <form method="post">
                      <td colspan="3"><?= $DHCPD_CONF ?> (header):<input type="hidden" name="f" value="dhcpheader">
                              <textarea style="width:100%;height:10em" name="dhcpheader"><?= $dhcph ?></textarea>
                      </td>
                      <td><input type="submit" value="save"></td>
                      <td></td>
              </form>
      </tr>
      
      <?php foreach($machine as $ip => $m):  ?>
          <?php  $name = @$m['name'];  ?>
          <?php  $mac = @$m['mac'];    ?>
          <?php  $class_reg = ($m['reg'] ? 'reg' : 'unreg');     ?>
          <tr class="<?= $class_reg ?>">
                      <form>
      
                      <td class="ip"><?= $ip ?>
                              <input type="hidden" name="h_ip" value="<?= $ip ?>">
                              <input type="hidden" name="f" value="editar">
                              <a id="<?= $ip ?>">&nbsp;</a>
                      </td>
                      <td><input type="text" name="h_name" value="<?= $name ?>"></td>
                      <td><input maxlength="17" type="text" name="h_mac" value="<?= $mac  ?>"></td>
                      <td><input type="submit" value="save"></td>
                      <td><!--<a href="?f=delete&h_ip=<?= $ip ?>">delete</a>--></td>
                      </form>
                    </tr>
      
      <?php endforeach; ?>
      <tr class="novo">
        <form action="">        
          <td><input type="hidden" name="f" value="novo">
          <input type="text" name="h_ip" value="">
          </td>
          <td><input type="text" name="h_name" value=""></td>
          <td><input maxlength="17" type="text" name="h_mac" value=""></td>
          <td><input type="submit" value="save"></td>
          <td></td>
        </form>
      </tr>
    
    </table>
  </body>
</html>

                                                  

                                  