<?php
/*
*   RoLinkX Dashboard v0.1b
*   Copyright (C) 2021 by Razvan Marin YO6NAM / www.xpander.ro
*
*   This program is free software; you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation; either version 2 of the License, or
*   (at your option) any later version.
*
*   This program is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*   along with this program; if not, write to the Free Software
*   Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
*/

/*
* Status reporting module
*/

if (isset($_GET['svxStatus'])) echo getSVXLinkStatus(1);
if (isset($_GET['svxReflector'])) echo getReflector();

/* Get IP(s) */
function networking() {
	$returnData = '';
	exec('ip route|grep \'link src \'', $reply);
	if (empty($reply)) return false;
	preg_match_all('/(eth0|\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', implode("\n", $reply), $lanData);
	preg_match_all('/(wlan0|\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', implode("\n", $reply), $wlanData);
	$lanIp	= (isset($lanData[0][2]) && preg_match('/^169\.254/', $lanData[0][2]) === 0) ? $lanData[0][2] : '' ;
	$wlanIp = (isset($wlanData[0][4])) ? $wlanData[0][4] : '' ;

	if (!empty($lanIp)) {
		$returnData .= '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;">LAN IP</span>
  		<input type="text" class="form-control" placeholder="'. $lanIp .'" readonly>
	</div>' . PHP_EOL;
	}
	if (!empty($wlanIp)) {
		$returnData .= '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;">WLAN IP</span>
  		<input type="text" class="form-control" placeholder="'. $wlanIp .'" readonly>
	</div>';
	}
	return $returnData;
}

/* Get Hostname */
function hostName() {
	return '<div class="input-group mb-2">
  		<!-- <span class="input-group-text" style="width: 6.5rem;">Host Name</span> -->
  		<button class="btn btn-dark" style="width: 6.5rem;" type="button" id="switchHostName">Host Name</button>
  		<input type="text" class="form-control" placeholder="'. gethostname() .'" readonly>
	</div>';
}

/* Uptime */
function getUpTime() {
	exec("uptime -p", $reply);
	$result = (empty($reply)) ? 'Not available' : substr($reply[0],3);
    return '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;">Uptime</span>
  		<input type="text" class="form-control" placeholder="'. $result .'" readonly>
	</div>';
}

/* CPU Load & Temp */
function getCpuStats() {
	$cpuLoad = getServerLoad();
	$avgLoad = (is_null($cpuLoad)) ? 'N/A' : number_format($cpuLoad, 2) . "%";
	exec("cat /etc/armbianmonitor/datasources/soctemp", $reply);
	if ($reply != 0) {
    	$cpuTempVal = substr($reply[0], 0, -3);
    	$cpuTemp = $cpuTempVal . '&deg;C';
    	$tempWarning = ($cpuTempVal > 50) ? 'bg-warning text-dark' : '';
    	return '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;">CPU</span>
  		<input type="text" class="form-control text-center" placeholder="'.$avgLoad.'" readonly>
  		<input type="text" class="form-control text-center '. $tempWarning .'" placeholder="'.$cpuTemp.'" readonly>
	</div>';
	}
}

function _getServerLoadLinuxData(){
    if (is_readable("/proc/stat")) {
        $stats = @file_get_contents("/proc/stat");
        if ($stats !== false) {
            $stats = preg_replace("/[[:blank:]]+/", " ", $stats);
            $stats = str_replace(array("\r\n", "\n\r", "\r"), "\n", $stats);
            $stats = explode("\n", $stats);
            foreach ($stats as $statLine) {
                $statLineData = explode(" ", trim($statLine));
                if ((count($statLineData) >= 5) && ($statLineData[0] == "cpu")) {
                    return array(
                        $statLineData[1],
                        $statLineData[2],
                        $statLineData[3],
                        $statLineData[4],
                    );
                }
            }
        }
    }
    return null;
}

function getServerLoad() {
    $load = null;
	if (is_readable("/proc/stat")) {
            $statData1 = _getServerLoadLinuxData();
            sleep(1);
            $statData2 = _getServerLoadLinuxData();
            if ((!is_null($statData1)) && (!is_null($statData2))) {
                $statData2[0] -= $statData1[0];
                $statData2[1] -= $statData1[1];
                $statData2[2] -= $statData1[2];
                $statData2[3] -= $statData1[3];
                $cpuTime = $statData2[0] + $statData2[1] + $statData2[2] + $statData2[3];
                $load = 100 - ($statData2[3] * 100 / $cpuTime);
            }
        }
    return $load;
}

/* Retreive SSID (if connected) */
function getSSID() {
	exec('/sbin/iwgetid --raw', $reply);
	if (isset($reply[0])) {
		$wifiStatus = $reply[0];
		$wifiMode = 'SSID';
	} else {
		exec('systemctl is-active hostapd', $mode);
		$wifiStatus = ($mode[0] == 'active') ? 'Hotspot' : 'Not associated' ;
		$wifiMode = 'Wi-Fi mode';
	}
	return '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;">'. $wifiMode .'</span>
  		<input type="text" class="form-control" placeholder="'. $wifiStatus .'" readonly>
	</div>';
}

/* Get Public IP */
function getPublicIP() {
	exec("dig @resolver4.opendns.com myip.opendns.com +short", $reply);
	$result = (empty($reply)) ? 'Not available' : $reply[0];
	$noIp	= (empty($reply)) ? '&nbsp;&nbsp;
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="red" class="bi bi-exclamation-circle" viewBox="0 0 16 16">
	<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
	<path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
	</svg>' : '';
    return '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;">External IP'. $noIp .'</span>
  		<input type="text" class="form-control" placeholder="'. $result .'" readonly>
	</div>';
}

/* Get SVXLink status */
function getSVXLinkStatus($update = 0) {
	exec("pgrep svxlink", $reply);
	$result = (empty($reply)) ? 'Not running' : 'Running ('. $reply[0] .')' ;
	if ($update) return $result;
	return '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;">SVXLink</span>
  		<input id="svxStatus" type="text" class="form-control" placeholder="'. $result .'" readonly>
	</div>';
}

/* Get Reflector address */
function getReflector() {
	$conStatus = $stateColor = '';
	preg_match('/HOST=(\S+)/', file_get_contents('/opt/rolink/conf/rolink.conf'), $reply);
	preg_match_all('/(ERROR|Disconnected|established)/', file_get_contents('/tmp/svxlink.log'), $logData);
	if (!empty($logData) && getSVXLinkStatus(1) != 'Not running') {
		$conStatus = $logData[0][array_key_last($logData[0])];
		switch ($conStatus) {
			case "established":
				$stateColor = 'background:lightgreen;';
    			break;
			case "Disconnected":
				$stateColor = 'background:tomato;';
				break;
			case "ERROR":
				$stateColor = 'background:red;';
				break;
		}
	}
	return '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;'. $stateColor .'">Reflector</span>
  		<input type="text" class="form-control" placeholder="'. $reply[1] .'" readonly>
	</div>';
}

/* Get SVX Callsign	*/
function getCallSign() {
	preg_match('/CALLSIGN=(\S+)/', file_get_contents('/opt/rolink/conf/rolink.conf'), $reply);
	return '<div class="input-group mb-2">
  		<span class="input-group-text" style="width: 6.5rem;">Call Sign</span>
  		<input type="text" class="form-control" placeholder="'. $reply[1] .'" readonly>
	</div>';
}
