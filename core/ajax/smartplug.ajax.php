<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

 function selectRead($socket, $sec = 0)
{
    $usec = $sec === null ? null : (($sec - floor($sec)) * 1000000);
    $r = array($socket);
    $ret = socket_select($r, $x, $x, $sec, $usec);
    if ($ret === false) {
        throw new Exception('Failed to select socket for reading');
    }
    return !!$ret;
}

function read($socket, $length)
{
    $len = socket_recvfrom($socket, $data, $length, MSG_WAITALL, $from, $port);
    if ($len === false) {
        throw new Exception('Read');
    }
    return array("data" => $data,
                 "length" => $len,
                 "from" => $from,
                 "port" => $port);
}

function encrypt($message)
{
    $strLength = strlen($message);
    $key = 171;
    for ($i = 0; $i < $strLength; $i++) {
        $a = ord($message[$i])^$key;
        $key = $a;
        $result .= chr($a);
    }
    return $result;
}

function decrypt($message)
{
    $strLength = strlen($message);
    $key = 171;
    for ($i = 0; $i < $strLength; $i++) {
        $a = ord($message[$i])^$key;
        $key = ord($message[$i]);
        $result .= chr($a);
    }
    return $result;
}

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }
    
    ajax::init();

    if (init('action') == 'discover') {
        $msg = encrypt('{"system":{"get_sysinfo":{}}}');
        $len = strlen($msg);
        
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_sendto($socket, $msg, $len, 0, '255.255.255.255', 9999);
        
        while (selectRead($socket, 2)) {
            $data = read($socket, 2048);
            $response = decrypt($data['data']);
            $dataArray = json_decode($response, true);
            $id = $dataArray['system']['get_sysinfo']['deviceId'];
            $smartplug = eqLogic::byLogicalId('smartplug_'.$id, 'smartplug', $_multiple = false);
            if ( !is_object($smartplug)) {
                $smartplug = new smartplug();
                $smartplug->setName('smartplug_'.$id);
                $smartplug->setLogicalId('smartplug_'.$id);
                $smartplug->setEqType_name('smartplug');
                $smartplug->setCategory('energy', '1');
            }
            $ip = $data['from'];
            $port = $data['port'];
            $smartplug->setConfiguration("addr",$ip);
            $smartplug->setConfiguration("port",$port);
            $smartplug->setConfiguration("model","HS110");
            $smartplug->save();
            log::add('smartplug','debug','Smart Plug '.$smartplug->getName().' is created');
        }
        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}

