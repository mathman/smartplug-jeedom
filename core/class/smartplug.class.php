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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class smartplug extends eqLogic {
    /*     * *************************Attributs****************************** */



    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */

    public static function decoupe($seconde)
    {
        $day=floor($seconde/86400);
        $seconde %=86400;
        $heure=floor($seconde/3600);
        $seconde %= 3600;
        $minute = floor($seconde/60);
        $seconde%=60;
        if( $day<10 )
            $day = "0".$day;
        if($heure <10 )
            $heure = "0".$heure;
        if($minute <10)
            $minute = "0".$minute;
        if($seconde <10)
            $seconde = "0".$seconde;

        return ["day" => $day,
                "hour" => $heure,
                "minute" => $minute,
                "second" => $seconde];
    }
    
    public static function encrypt($message)
    {
        $result = pack('N', strlen($message));
        $strLength = strlen($message);
        $key = 171;
        for ($i = 0; $i < $strLength; $i++) {
            $a = ord($message[$i])^$key;
            $key = $a;
            $result .= chr($a);
        }
        return $result;
    }

    public static function decrypt($message)
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
    
    public static function sendCommand($ip, $port, $data) {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            log::add('smartplug','debug', 'Erreur à la création : '.socket_strerror(socket_last_error($socket)));
        } else {
            log::add('smartplug','debug', 'Socket is created');
            $connected = socket_connect($socket, $ip, $port);
            if ($connected) {
                log::add('smartplug','debug', 'Socket is connected');
                $dataEncrypted = smartplug::encrypt($data);

                $ret = socket_send($socket, $dataEncrypted, strlen($dataEncrypted), 0);
                if ($ret === false) {
                    log::add('smartplug','debug', 'Erreur à l\'envoie : '.socket_strerror(socket_last_error($socket)));
                } else {
                    log::add('smartplug','debug', 'Socket is send');
                    
                    $bufferEncrypted = socket_read($socket, 2048);
                    if ($bufferEncrypted === false) {
                        log::add('smartplug','debug', 'Erreur à la lecture : '.socket_strerror(socket_last_error($socket)));
                    } else if ($bufferEncrypted==="") {
                        log::add('smartplug','debug', 'Client déconnecté');
                    } else if ($bufferEncrypted=="") {
                        log::add('smartplug','debug', 'Connecté mais aucune donnée reçue ou client déconnecté');
                    } else {
                        log::add('smartplug','debug', 'Socket is read');
                    }
                    socket_close($socket);
                    
                    if ($bufferEncrypted) {
                        $response = smartplug::decrypt(substr($bufferEncrypted, 4));
                        log::add('smartplug','debug', $response);
                        return json_decode($response, true);
                    } else {
                        return false;
                    }
                }
            } else {
                log::add('smartplug','debug', 'Erreur à la connexion : '.socket_strerror(socket_last_error($socket)));
            }
        }
        return false;
    }
    
    public static function pull() {
		foreach (self::byType('smartplug') as $eqLogic) {
			$eqLogic->refresh();
		}
	}

    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    public function postSave() {
        
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
        if ( $this->getIsEnable() )
		{
            /* refresh */
            $refresh = $this->getCmd(null, 'refresh');
            if (!is_object($refresh)) {
                $refresh = new smartplugCmd();
            }
            $refresh->setOrder(0);
            $refresh->setLogicalId('refresh');
            $refresh->setName('Rafraichir');
            $refresh->setType('action');
            $refresh->setSubType('other');
            $refresh->setEqLogic_id($this->getId());
            $refresh->save();

            /* etat */
            $etat = $this->getCmd(null, 'etat');
            if (!is_object($etat)) {
                $etat = new smartplugCmd();
            }
            $etat->setOrder(1);
            $etat->setLogicalId('etat');
            $etat->setName('Etat');
            $etat->setType('info');
            $etat->setDisplay('generic_type','ENERGY_STATE');
            $etat->setSubType('binary');
            $etat->setEqLogic_id($this->getId());
            $etat->setTemplate('dashboard', 'prise');
            $etat->setTemplate('mobile', 'prise');
            $etat->setDisplay('showNameOndashboard', '0');
            $etat->setDisplay('showNameOnplan', '0');
            $etat->setDisplay('showNameOnview', '0');
            $etat->setDisplay('showNameOnmobile', '0');
            $etat->setDisplay('forceReturnLineAfter', '1');
            $etat->save();
            
            /* -- pour HSS110 ---*/
            $model = $this->getConfiguration('model');
            if($model == 'HS110')
            {
                /* voltage */
                $voltage = $this->getCmd(null, 'voltage');
                if (!is_object($voltage)) {
                    $voltage = new smartplugCmd();
                }
                $voltage->setOrder(2);
                $voltage->setLogicalId('voltage');
                $voltage->setName('Tension');
                $voltage->setType('info');
                $voltage->setSubType('numeric');
                $voltage->setEqLogic_id($this->getId());
                $voltage->setTemplate('dashboard','tile');
                $voltage->setTemplate('mobile','tile');
                $voltage->setUnite('V');
                $voltage->setIsHistorized(1);
                $voltage->save();
                
                /* current */
                $current = $this->getCmd(null, 'current');
                if (!is_object($current)) {
                    $current = new smartplugCmd();
                }
                $current->setOrder(3);
                $current->setLogicalId('current');
                $current->setName('Courant');
                $current->setType('info');
                $current->setSubType('numeric');
                $current->setEqLogic_id($this->getId());
                $current->setDisplay('forceReturnLineAfter', '1');
                $current->setTemplate('dashboard','tile');
                $current->setTemplate('mobile','tile');
                $current->setUnite('mA');
                $current->setIsHistorized(1);
                $current->save();
                
                /* power */
                $power = $this->getCmd(null, 'power');
                if (!is_object($power)) {
                    $power = new smartplugCmd();
                }
                $power->setOrder(4);
                $power->setLogicalId('power');
                $power->setName('Puissance');
                $power->setType('info');
                $power->setSubType('numeric');
                $power->setEqLogic_id($this->getId());
                $power->setDisplay('forceReturnLineAfter', '1');
                $power->setTemplate('dashboard','tile');
                $power->setTemplate('mobile','tile');
                $power->setUnite('W');
                $power->setIsHistorized(1);
                $power->save();
            }
            
            /*   add currentRunTime en seconde */
            $currentRunTime = $this->getCmd(null, 'currentRunTime');
            if (!is_object($currentRunTime)) {
                $currentRunTime = new smartplugCmd();
            }
            $currentRunTime->setOrder(5);
            $currentRunTime->setLogicalId('currentRunTime');
            $currentRunTime->setName('Durée : ');
            $currentRunTime->setType('info');
            $currentRunTime->setSubType('string');
            $currentRunTime->setEqLogic_id($this->getId());
            $currentRunTime->setDisplay('forceReturnLineAfter', '1');
            $currentRunTime->save();
            
            if($model == 'HS110')
            {
                /* add conso */
                $conso = $this->getCmd(null, 'conso');
                if (!is_object($conso)) {
                    $conso = new smartplugCmd();
                }
                $conso->setOrder(6);
                $conso->setLogicalId('conso');
                $conso->setName('Consommation totale');
                $conso->setType('info');
                $conso->setSubType('numeric');
                $conso->setEqLogic_id($this->getId());
                $conso->setDisplay('forceReturnLineAfter', '1');
                $conso->setTemplate('dashboard','tile');
                $conso->setTemplate('mobile','tile');
                $conso->setUnite('W/h');
                $conso->save();
            }
            
            $updatetime = $this->getCmd(null, 'updatetime');
			if ( ! is_object($updatetime)) {
				$updatetime = new smartplugCmd();
            }
            $updatetime->setName('Dernier refresh');
            $updatetime->setEqLogic_id($this->getId());
            $updatetime->setLogicalId('updatetime');
            $updatetime->setOrder(7);
            $updatetime->setType('info');
            $updatetime->setSubType('string');
            $updatetime->save();
        }
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }
    
    public function getInfo() {
        $data = '{"system":{"get_sysinfo":{}}}';
        $response = smartplug::sendCommand($this->getConfiguration('addr',''), $this->getConfiguration('port',''), $data);
        return $response;
    }
    
    public function getRealtime() {
        $data = '{"emeter":{"get_realtime":{}}}';
        $response = smartplug::sendCommand($this->getConfiguration('addr',''), $this->getConfiguration('port',''), $data);
        return $response;
    }
    
    public function refresh() {
        if ( $this->getIsEnable() ) {
            $eqpNetwork = eqLogic::byTypeAndSearhConfiguration('networks', $this->getConfiguration('addr'))[0];
            if (is_object($eqpNetwork)) {
                $statusCmd = $eqpNetwork->getCmd(null, 'ping');
                if (is_object($statusCmd) && $statusCmd->execCmd() == $statusCmd->formatValue(true))
                {
                    $infos = $this->getInfo();
                    $etat = $this->getCmd(null, 'etat');
                    if (is_object($etat)) {
                        if ($etat->formatValue($infos['system']['get_sysinfo']['relay_state']) != $etat->execCmd()) {
                            $etat->setCollectDate('');
                            $etat->event($infos['system']['get_sysinfo']['relay_state']);
                        }
                    }
                    $currentRunTimeCmd = $this->getCmd(null, 'currentRunTime');
                    if (is_object($currentRunTimeCmd)) {
                        $currentRunTime = smartplug::decoupe($infos['system']['get_sysinfo']['on_time']);
                        $currentRunTimeString = $currentRunTime['day'].'j '.$currentRunTime['hour'].'h '.$currentRunTime['minute'].'m '.$currentRunTime['second'].'s';
                        if ($currentRunTimeCmd->formatValue($currentRunTimeString) != $currentRunTimeCmd->execCmd()) {
                            $currentRunTimeCmd->setCollectDate('');
                            $currentRunTimeCmd->event($currentRunTimeString);
                        }
                    }
                    
                    /* -- pour HSS110 ---*/
                    $model = $this->getConfiguration('model');
                    if($model == 'HS110')
                    {
                        $realtime = $this->getRealtime();
                        $voltageCmd = $this->getCmd(null, 'voltage');
                        if (is_object($voltageCmd)) {
                            $voltage = round($realtime['emeter']['get_realtime']['voltage_mv']/1000, 2);
                            if ($voltageCmd->formatValue($voltage) != $voltageCmd->execCmd()) {
                                $voltageCmd->setCollectDate('');
                                $voltageCmd->event($voltage);
                            }
                        }
                        $currentCmd = $this->getCmd(null, 'current');
                        if (is_object($currentCmd)) {
                            $current = $realtime['emeter']['get_realtime']['current_ma'];
                            if ($currentCmd->formatValue($current) != $currentCmd->execCmd()) {
                                $currentCmd->setCollectDate('');
                                $currentCmd->event($current);
                            }
                        }
                        $powerCmd = $this->getCmd(null, 'power');
                        if (is_object($powerCmd)) {
                            $currentpower = round($realtime['emeter']['get_realtime']['power_mw']/1000, 2);
                            if ($powerCmd->formatValue($currentpower) != $powerCmd->execCmd()) {
                                $powerCmd->setCollectDate('');
                                $powerCmd->event($currentpower);
                            }
                        }
                        $consoCmd = $this->getCmd(null, 'conso');
                        if (is_object($consoCmd)) {
                            $conso = $realtime['emeter']['get_realtime']['total_wh'];
                            if ($consoCmd->formatValue($conso) != $consoCmd->execCmd()) {
                                $consoCmd->setCollectDate('');
                                $consoCmd->event($conso);
                            }
                        }
                    }
                    
                    $refresh = $this->getCmd(null, 'updatetime');
                    $refresh->event(date("d/m/Y H:i",(time())));
                    $mc = cache::byKey('smartplugWidgetmobile' . $this->getId());
                    $mc->remove();
                    $mc = cache::byKey('smartplugWidgetdashboard' . $this->getId());
                    $mc->remove();
                    $this->toHtml('mobile');
                    $this->toHtml('dashboard');
                    $this->refreshWidget();
                } else {
                    $etat = $this->getCmd(null, 'etat');
                    if (is_object($etat)) {
                        $etat->setCollectDate('');
                        $etat->event(0);
                    }
                }
            }
        }
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class smartplugCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
            throw new Exception(__('Equipement desactivé impossible d\éxecuter la commande : ' . $this->getHumanName(), __FILE__));
        }
		log::add('smartplug','debug','get '.$this->getLogicalId());
		switch ($this->getLogicalId()) {
            case "refresh":
                $eqLogic->refresh();
                break;
		}
        return true;
    }

    /*     * **********************Getteur Setteur*************************** */
}


