<?php

declare(strict_types=1);

require_once IPS_GetScriptFile(GetLocalConfig('GLOBAL_HELPER'));
require_once IPS_GetScriptFile(GetLocalConfig('VARIABLE_HELPER'));

$scriptName = IPS_GetName($_IPS['SELF']) . '(' . $_IPS['SELF'] . ')';
$scriptInfo = IPS_GetName(IPS_GetParent($_IPS['SELF'])) . '\\' . IPS_GetName($_IPS['SELF']);

function Inventory_Cmp($a, $b)
{
    $a_pos = $a['floorPos'];
    $b_pos = $b['floorPos'];
    if ($a_pos != $b_pos) {
        return $a_pos - $b_pos;
    }
    $a_name = $a['floorName'];
    $b_name = $b['floorName'];
    if ($a_name != $b_name) {
        return (strcmp($a_name, $b_name) < 0) ? -1 : 1;
    }

    $a_pos = $a['roomPos'];
    $b_pos = $b['roomPos'];
    if ($a_pos != $b_pos) {
        return $a_pos - $b_pos;
    }
    $a_name = $a['roomName'];
    $b_name = $b['roomName'];
    if ($a_name != $b_name) {
        return (strcmp($a_name, $b_name) < 0) ? -1 : 1;
    }

    $a_pos = $a['devPos'];
    $b_pos = $b['devPos'];
    if ($a_pos != $b_pos) {
        return $a_pos - $b_pos;
    }
    $a_name = $a['devName'];
    $b_name = $b['devName'];
    if ($a_name != $b_name) {
        return (strcmp($a_name, $b_name) < 0) ? -1 : 1;
    }

    $a_id = $a['devId'];
    $b_id = $b['devId'];
    return $a_id - $b_id;
}

function getChilds($objID, $chldIDs)
{
    $objIDs = IPS_GetChildrenIDs($objID);
    foreach ($objIDs as $j => $objID) {
        $obj = IPS_GetObject($objID);
        switch ($obj['ObjectType']) {
            case OBJECTTYPE_INSTANCE:
                $inst = IPS_GetInstance($objID);
                if ($inst['ModuleInfo']['ModuleID'] == '{485D0419-BE97-4548-AA9C-C083EB82E61E}') {
                    $chldIDs = getChilds($objID, $chldIDs);
                } else {
                    $chldIDs[] = $objID;
                }
                break;
            case OBJECTTYPE_CATEGORY:
                $chldIDs = getChilds($objID, $chldIDs);
                break;
        }
    }
    return $chldIDs;
}

function Inventory_Check()
{
    $roomVisu = [];
    $real_roomIDs = IPS_GetChildrenIDs(GetLocalConfig('Räume'));
    $floorIDs = IPS_GetChildrenIDs(GetLocalConfig('Drinnen'));
    foreach ($floorIDs as $floorID) {
        $floor = IPS_GetObject($floorID);
        $roomIDs = $floor['ChildrenIDs'];
        foreach ($roomIDs as $roomID) {
            $room = IPS_GetObject($roomID);
            $roomPos = $room['ObjectPosition'];
            if ($room['ObjectType'] == OBJECTTYPE_LINK) {
                $lnk = IPS_GetLink($roomID);
                $roomID = $lnk['TargetID'];
                $room = IPS_GetObject($roomID);
            }
            foreach ($real_roomIDs as $real_roomID) {
                $real_room = IPS_GetObject($real_roomID);
                if ($room['ObjectName'] == $real_room['ObjectName']) {
                    $roomID = $real_roomID;
                    $room = $real_room;
                    break;
                }
            }
            $roomVisu[$roomID] = [
                'floorId'   => $floor['ObjectID'],
                'floorPos'  => $floor['ObjectPosition'],
                'floorName' => $floor['ObjectName'],
                'roomId'    => $roomID,
                'roomPos'   => $roomPos,
                'roomName'  => $room['ObjectName'],
            ];
        }
    }
    $vpos = 1000;
    $floor = IPS_GetObject(GetLocalConfig('Draussen'));
    $roomIDs = $floor['ChildrenIDs'];
    foreach ($roomIDs as $roomID) {
        $room = IPS_GetObject($roomID);
        $roomPos = $room['ObjectPosition'];
        if ($room['ObjectType'] == OBJECTTYPE_LINK) {
            $lnk = IPS_GetLink($roomID);
            $roomID = $lnk['TargetID'];
            $room = IPS_GetObject($roomID);
        }
        foreach ($real_roomIDs as $real_roomID) {
            $real_room = IPS_GetObject($real_roomID);
            if ($room['ObjectName'] == $real_room['ObjectName']) {
                $roomID = $real_roomID;
                $room = $real_room;
                break;
            }
        }
        $roomVisu[$roomID] = [
            'floorId'   => $floor['ObjectID'],
            'floorPos'  => $floor['ObjectPosition'] + $vpos,
            'floorName' => $floor['ObjectName'],
            'roomId'    => $roomID,
            'roomPos'   => $roomPos,
            'roomName'  => $room['ObjectName'],
        ];
    }

    $addr2info = [];

    $roomIDs = IPS_GetChildrenIDs(GetLocalConfig('Räume'));
    foreach ($roomIDs as $roomID) {
        $room = IPS_GetObject($roomID);
        if (isset($roomVisu[$roomID])) {
            $visu = $roomVisu[$roomID];
            $floorName = $visu['floorName'];
        } else {
            // continue;

            $floorName = 'unknown';
            $visu = [
                'floorId'   => 0,
                'floorPos'  => 2000,
                'floorName' => 'ohne Etage',
                'roomId'   	=> $room['ObjectID'],
                'roomPos'   => $room['ObjectPosition'],
                'roomName'  => $room['ObjectName'],
            ];
            $roomVisu[$roomID] = $visu;
        }

        $devIDs = getChilds($roomID, []);
        foreach ($devIDs as $devID) {
            $dev = IPS_GetObject($devID);
            if ($dev['ObjectType'] != OBJECTTYPE_INSTANCE) {
                continue;
            }
            $inst = IPS_GetInstance($devID);
            if ($inst['ModuleInfo']['ModuleID'] != '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}' /* HomeMatic */) {
                continue;
            }
            $addr = IPS_GetProperty($devID, 'Address');
            $sub = preg_replace('/^[^:]*:/', '', $addr);
            $addr = preg_replace('/:[0-9]*$/', '', $addr);
            $type = Util_Gerate2Typ($devID);

            if ($type == '') {
                continue;
            }
            if ($sub != 0) {
                continue;
            }
            $devName = $dev['ObjectName'];
            $devName = preg_replace('/ \(:[0-9]*\)$/', '', $devName);

            $parID = $dev['ParentID'];
            if ($parID != $roomID) {
                $devName = IPS_GetName($parID) . '\\' . $devName;
            }

            $info = [
                'addr'      => $addr,
                'type'      => $type,

                'floorId'   => $visu['floorId'],
                'floorPos'  => $visu['floorPos'],
                'floorName' => $visu['floorName'],
                'roomId'    => $visu['roomId'],
                'roomPos'   => $visu['roomPos'],
                'roomName'  => $visu['roomName'],
                'devId'     => $devID,
                'devPos'    => $dev['ObjectPosition'],
                'devName'   => $devName,
            ];
            $addr2info[$addr] = $info;
        }
    }

    $n_lowbat = 0;
    $n_config_pending = 0;
    $n_unreach = 0;
    $n_duty_cycle = 0;
    $n_valve_state = 0;

    $instIDs = IPS_GetInstanceListByModuleID('{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}');  // HomeMatic
    foreach ($instIDs as $instID) {
        $inst = IPS_GetInstance($instID);
        $addr = IPS_GetProperty($instID, 'Address');
        $chan = preg_replace('/^[^:]*:/', '', $addr);
        $addr = preg_replace('/:[0-9]*$/', '', $addr);
        $info = isset($addr2info[$addr]) ? $addr2info[$addr] : [];

        if (!isset($info['type'])) {
            continue;
        }
        $type = $info['type'];

        $varIDs = isset($info['varIDs']) ? $info['varIDs'] : [];

        $do_lowbat = true;
        switch ($type) {
            case 'HM-SEC-SC-2':
            case 'HM-SEC-SCO':
            case 'HM-SEC-RHS':
                if ($chan == '0') {
                    $do_lowbat = false;
                }
                break;
            default:
                break;
        }

        if ($do_lowbat) {
            $varID = @IPS_GetObjectIDByIdent('LOWBAT', $instID);
            if ($varID == false) {
                $varID = @IPS_GetObjectIDByIdent('LOW_BAT', $instID);
            }
            if ($varID != false) {
                $var = IPS_GetVariable($varID);

                $val = $var['VariableValue'];
                $lastUpdate = $var['VariableUpdated'];

                $elem = [
                    'varId'      => $varID,
                    'value'      => $val,
                    'lastUpdate' => $lastUpdate,
                ];

                $info['lowbat'] = $elem;
                $varIDs[] = $varID;
                if ($val == true) {
                    $n_lowbat++;
                }
            }
        }

        $do_config_pending = true;
        if ($do_config_pending) {
            $varID = @IPS_GetObjectIDByIdent('CONFIG_PENDING', $instID);
            if ($varID != false) {
                $var = IPS_GetVariable($varID);

                $val = $var['VariableValue'];
                $lastUpdate = $var['VariableUpdated'];

                $elem = [
                    'varId'      => $varID,
                    'value'      => $val,
                    'lastUpdate' => $lastUpdate,
                ];

                $info['config_pending'] = $elem;
                $varIDs[] = $varID;
                if ($val == true) {
                    $n_config_pending++;
                }
            }
        }

        $do_unreach = true;
        if ($do_unreach) {
            $varID = @IPS_GetObjectIDByIdent('UNREACH', $instID);
            if ($varID != false) {
                $var = IPS_GetVariable($varID);

                $val = $var['VariableValue'];
                $lastUpdate = $var['VariableUpdated'];

                $elem = [
                    'varId'      => $varID,
                    'value'      => $val,
                    'lastUpdate' => $lastUpdate,
                ];

                $info['unreach'] = $elem;
                $varIDs[] = $varID;
                if ($val == true) {
                    $n_unreach++;
                }
            }
        }

        $do_rssi = true;
        if ($do_rssi) {
            $varID = @IPS_GetObjectIDByIdent('RSSI_DEVICE', $instID);
            if ($varID != false) {
                $var = IPS_GetVariable($varID);

                $val = $var['VariableValue'];
                $lastUpdate = $var['VariableUpdated'];

                $elem = [
                    'varId'      => $varID,
                    'value'      => $val,
                    'lastUpdate' => $lastUpdate,
                ];

                $info['rssi_device'] = $elem;
                $varIDs[] = $varID;
            }
            $varID = @IPS_GetObjectIDByIdent('RSSI_PEER', $instID);
            if ($varID != false) {
                $var = IPS_GetVariable($varID);

                $val = $var['VariableValue'];
                $lastUpdate = $var['VariableUpdated'];

                $elem = [
                    'varId'      => $varID,
                    'value'      => $val,
                    'lastUpdate' => $lastUpdate,
                ];

                $info['rssi_peer'] = $elem;
                $varIDs[] = $varID;
            }
        }

        $do_duty_cycle = true;
        if ($do_duty_cycle) {
            $varID = @IPS_GetObjectIDByIdent('DUTY_CYCLE', $instID);
            if ($varID == false) {
                $varID = @IPS_GetObjectIDByIdent('DUTYCYCLE', $instID);
            }
            if ($varID != false) {
                $var = IPS_GetVariable($varID);

                $val = $var['VariableValue'];
                $lastUpdate = $var['VariableUpdated'];

                $elem = [
                    'varId'      => $varID,
                    'value'      => $val,
                    'lastUpdate' => $lastUpdate,
                ];

                $info['duty_cycle'] = $elem;
                $varIDs[] = $varID;
                if ($val == true) {
                    $n_duty_cycle++;
                }
            }
        }

        $do_valve_state = true;
        if ($do_valve_state) {
            $varID = @IPS_GetObjectIDByIdent('VALVE_STATE', $instID);
            if ($varID != false) {
                $var = IPS_GetVariable($varID);

                $val = in_array($var['VariableValue'], [3 /* Adaptionsfahrt läuft */, 4 /* Adaptionsfahrt abgeschlossen */]) == false;
                $lastUpdate = $var['VariableUpdated'];

                $elem = [
                    'varId'      => $varID,
                    'value'      => $val,
                    'lastUpdate' => $lastUpdate,
                ];

                $info['valve_state'] = $elem;
                $varIDs[] = $varID;
                if ($val == true) {
                    $n_valve_state++;
                }
            }
        }

        $info['varIDs'] = $varIDs;

        $addr2info[$addr] = $info;
    }

    usort($addr2info, 'Inventory_Cmp');

    $r = [
        'infos'           => $addr2info,
        'lowbat'          => $n_lowbat,
        'config_pending'  => $n_config_pending,
        'unreach'         => $n_unreach,
        'duty_cycle'      => $n_duty_cycle,
        'valve_state'     => $n_valve_state,
    ];
    return $r;
}

function Inventory_AdjustEvents4SmokeDetector()
{
    $scriptID = @IPS_GetObjectIDByName('Rauchmelder-Auslösung melden', GetLocalConfig('Geräte-Status'));
    if ($scriptID != false) {
        $varIDs = [];

        $catID = IPS_GetObjectIDByName('HmIP-SWSD', GetLocalConfig('Geräte-Typen'));
        $objIDs = IPS_GetChildrenIDs($catID);
        foreach ($objIDs as $objID) {
            $lnk = IPS_GetLink($objID);
            $objID = $lnk['TargetID'];
            $addr = IPS_GetProperty($objID, 'Address');
            $name = preg_replace('/ \(:[0-9]*\)$/', '', IPS_GetName($objID));

            foreach (['SMOKE_DETECTOR_ALARM_STATUS'] as $ident) {
                $varID = @IPS_GetObjectIDByIdent($ident, $objID);
                if ($varID != false) {
                    $varIDs[] = $varID;
                }
            }
        }

        $script = IPS_GetObject($scriptID);

        $triggerIDs = [];
        $triggerID2eventID = [];
        $chldIDs = $script['ChildrenIDs'];
        foreach ($chldIDs as $chldID) {
            $chld = IPS_GetObject($chldID);
            if ($chld['ObjectType'] != OBJECTTYPE_EVENT) {
                continue;
            }
            $event = IPS_GetEvent($chldID);
            $triggerID = $event['TriggerVariableID'];
            $triggerIDs[] = $triggerID;
            $triggerID2eventID[$triggerID] = $chldID;
        }

        foreach ($varIDs as $varID) {
            $varIDs[] = $varID;
            if (in_array($varID, $triggerIDs)) {
                $eventID = $triggerID2eventID[$varID];
                echo '  preserve eventID ' . $eventID . ' for varID ' . $varID . PHP_EOL;
                continue;
            }
            $eventID = IPS_CreateEvent(0);
            IPS_SetParent($eventID, $scriptID);
            IPS_SetEventTrigger($eventID, 1, $varID);
            IPS_SetEventAction($eventID, '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}', []);
            IPS_SetEventActive($eventID, true);
            echo '  create eventID ' . $eventID . ' for varID ' . $varID . PHP_EOL;
        }
        foreach ($triggerIDs as $triggerID) {
            if (in_array($triggerID, $varIDs)) {
                continue;
            }
            $eventID = $triggerID2eventID[$triggerID];
            IPS_DeleteEvent($eventID);
            echo '  delete eventID ' . $eventID . ' for varID ' . $triggerID . PHP_EOL;
        }
    }

    $scriptID = @IPS_GetObjectIDByName('Rauchmelder-Status melden', GetLocalConfig('Geräte-Status'));
    if ($scriptID != false) {
        $varIDs = [];

        $catID = IPS_GetObjectIDByName('HmIP-SWSD', GetLocalConfig('Geräte-Typen'));
        $objIDs = IPS_GetChildrenIDs($catID);
        foreach ($objIDs as $objID) {
            $lnk = IPS_GetLink($objID);
            $objID = $lnk['TargetID'];
            $addr = IPS_GetProperty($objID, 'Address');
            $name = preg_replace('/ \(:[0-9]*\)$/', '', IPS_GetName($objID));

            foreach (['ERROR_DEGRADED_CHAMBER'] as $ident) {
                $varID = @IPS_GetObjectIDByIdent($ident, $objID);
                if ($varID != false) {
                    $varIDs[] = $varID;
                }
            }
        }

        $script = IPS_GetObject($scriptID);

        $triggerIDs = [];
        $triggerID2eventID = [];
        $chldIDs = $script['ChildrenIDs'];
        foreach ($chldIDs as $chldID) {
            $chld = IPS_GetObject($chldID);
            if ($chld['ObjectType'] != OBJECTTYPE_EVENT) {
                continue;
            }
            $event = IPS_GetEvent($chldID);
            $triggerID = $event['TriggerVariableID'];
            $triggerIDs[] = $triggerID;
            $triggerID2eventID[$triggerID] = $chldID;
        }

        foreach ($varIDs as $varID) {
            $varIDs[] = $varID;
            if (in_array($varID, $triggerIDs)) {
                $eventID = $triggerID2eventID[$varID];
                echo '  preserve eventID ' . $eventID . ' for varID ' . $varID . PHP_EOL;
                continue;
            }
            $eventID = IPS_CreateEvent(0);
            IPS_SetParent($eventID, $scriptID);
            IPS_SetEventTrigger($eventID, 1, $varID);
            IPS_SetEventAction($eventID, '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}', []);
            IPS_SetEventActive($eventID, true);
            echo '  create eventID ' . $eventID . ' for varID ' . $varID . PHP_EOL;
        }
        foreach ($triggerIDs as $triggerID) {
            if (in_array($triggerID, $varIDs)) {
                continue;
            }
            $eventID = $triggerID2eventID[$triggerID];
            IPS_DeleteEvent($eventID);
            echo '  delete eventID ' . $eventID . ' for varID ' . $triggerID . PHP_EOL;
        }
    }
}

function Inventory_Print($infoList)
{
    $infos = $infoList['infos'];
    $n_lowbat = $infoList['lowbat'];
    $n_config_pending = $infoList['config_pending'];
    $n_unreach = $infoList['unreach'];
    $n_duty_cycle = $infoList['duty_cycle'];
    $n_valve_state = $infoList['valve_state'];

    echo 'LOWBAT=' . $n_lowbat . ', CONFIG_PENDING=' . $n_config_pending . ', UNREACH=' . $n_unreach . ', DUTY_CYCLE=' . $n_duty_cycle . ', VALVE_STATE=' . $n_valve_state . PHP_EOL;

    foreach ($infos as $info) {
        $addr = $info['addr'];
        $type = $info['type'];
        $floorName = $info['floorName'];
        $roomName = $info['roomName'];
        $devName = $info['devName'];
        $mark_lowbat = isset($info['lowbat']) && $info['lowbat']['value'] ? 'LOWBAT' : '';
        $mark_config = isset($info['config_pending']) && $info['config_pending']['value'] ? 'CONFIG' : '';
        $mark_unreach = isset($info['unreach']) && $info['unreach']['value'] ? 'UNREACH' : '';
        $mark_duty_cycle = isset($info['duty_cycle']) && $info['duty_cycle']['value'] ? 'DUTY_CYCLE' : '';
        $mark_valve_state = isset($info['valve_state']) && $info['valve_state']['value'] ? 'VALVE_STATE' : '';

        echo $floorName . '\\' . $roomName . '\\' . $devName . ', type=' . $type . ', addr=' . $addr . ' => ' . $mark_lowbat . ' ' . $mark_config . ' ' . $mark_unreach . ' ' . $mark_duty_cycle . ' ' . $mark_valve_state . PHP_EOL;
    }

    echo PHP_EOL;
}

function Inventory_AdjustEvents()
{
    $r = Inventory_Check();
    $infos = $r['infos'];

    $scriptID = IPS_GetObjectIDByName('Status ermitteln', GetLocalConfig('Geräte-Status'));
    $script = IPS_GetObject($scriptID);

    $triggerIDs = [];
    $triggerID2eventID = [];
    $chldIDs = $script['ChildrenIDs'];
    foreach ($chldIDs as $chldID) {
        $chld = IPS_GetObject($chldID);
        if ($chld['ObjectType'] != OBJECTTYPE_EVENT) {
            continue;
        }
        $event = IPS_GetEvent($chldID);
        $triggerID = $event['TriggerVariableID'];
        $triggerIDs[] = $triggerID;
        $triggerID2eventID[$triggerID] = $chldID;
    }
    $varIDs = [];
    foreach ($infos as $info) {
        foreach ($info['varIDs'] as $varID) {
            $varIDs[] = $varID;
            if (in_array($varID, $triggerIDs)) {
                $eventID = $triggerID2eventID[$varID];
                echo '  preserve eventID ' . $eventID . ' for varID ' . $varID . PHP_EOL;
                continue;
            }
            $eventID = IPS_CreateEvent(0);
            IPS_SetParent($eventID, $scriptID);
            IPS_SetEventTrigger($eventID, 1, $varID);
            IPS_SetEventAction($eventID, '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}', []);
            IPS_SetEventActive($eventID, true);
            echo '  create eventID ' . $eventID . ' for varID ' . $varID . PHP_EOL;
        }
    }
    foreach ($triggerIDs as $triggerID) {
        if (in_array($triggerID, $varIDs)) {
            continue;
        }
        $eventID = $triggerID2eventID[$triggerID];
        IPS_DeleteEvent($eventID);
        echo '  delete eventID ' . $eventID . ' for varID ' . $triggerID . PHP_EOL;
    }
}

function Inventory_AdjustArchive()
{
    $ArchivID = GetLocalConfig('Archive Control');

    $r = Inventory_Check();
    $infos = $r['infos'];
    foreach ($infos as $info) {
        foreach (['rssi_device', 'rssi_peer'] as $s) {
            $v = $info[$s];
            $varID = $v['varId'];
            if (AC_GetLoggingStatus($ArchivID, $varID) == false) {
                AC_SetLoggingStatus($ArchivID, $varID, true);
                echo '  set logging for varID ' . $varID . PHP_EOL;
            }
        }
    }
}

function Inventory_Calculate()
{
    $r = Inventory_Check();
    $infos = $r['infos'];

    $total = count($infos);
    $n_lowbat = $r['lowbat'];
    $n_config_pending = $r['config_pending'];
    $n_unreach = $r['unreach'];
    $n_duty_cycle = $r['duty_cycle'];
    $n_valve_state = $r['valve_state'];

    $html = Inventory_BuildHtmlBox($infos);

    $catID = GetLocalConfig('Geräte-Status');

    $varID = Variable_Create($catID, '', 'Anzahl Geräte', VARIABLETYPE_INTEGER, '', 0, 0);
    SetValueInteger($varID, $total);

    $varID = @IPS_GetObjectIDByName('Batteriestand zu niedrig', $catID);
    SetValueInteger($varID, $n_lowbat);

    $varID = Variable_Create($catID, '', 'Konfiguration ausstehend', VARIABLETYPE_INTEGER, '', 0, 0);
    SetValueInteger($varID, $n_config_pending);

    $varID = Variable_Create($catID, '', 'nicht erreichbar', VARIABLETYPE_INTEGER, '', 0, 0);
    SetValueInteger($varID, $n_unreach);

    $varID = Variable_Create($catID, '', 'Duty-Cycle erreicht', VARIABLETYPE_INTEGER, '', 0, 0);
    SetValueInteger($varID, $n_duty_cycle);

    $varID = Variable_Create($catID, '', 'Ventilstatus fehlerhaft', VARIABLETYPE_INTEGER, '', 0, 0);
    SetValueInteger($varID, $n_valve_state);

    $varID = Variable_Create($catID, '', 'Übersicht', VARIABLETYPE_STRING, '~HTMLBox', 0, 0);
    SetValueString($varID, $html);

    IPS_LogMessage(__FUNCTION__, 'total=' . $total . ', lowbat=' . $n_lowbat . ', config=' . $n_config_pending . ', unreach=' . $n_unreach . ', duty_cycle=' . $n_duty_cycle . ', valve_state=' . $n_valve_state);
}

function Inventory_rssi_one_color($lower_bound, $upper_bound, $rssi)
{
    $result = 256 * ($rssi - $lower_bound) / ($upper_bound - $lower_bound);
    if ($result < 0) {
        $result = 0;
    }
    if ($result > 255) {
        $result = 255;
    }
    return intval($result);
}

function Inventory_rssi_color($rssi)
{
    if ($rssi < 65536) {
        $red = rssi_one_color(-20, -100, $rssi);
        $green = rssi_one_color(-120, -100, $rssi);
    } else {
        $red = 0;
        $green = 0;
    }
    return sprintf('#%02X%02X00', $red, $green);
}

function Inventory_BuildHtmlBox($infos)
{
    $detailed = false;

    $html = '';
    $html .= '<style>' . PHP_EOL;
    $html .= 'body { margin: 1; padding: 0; font-family: "Open Sans", sans-serif; font-size: 20px; }' . PHP_EOL;
    $html .= 'table { border-collapse: collapse; border: 0px solid; margin: 0.5em;}' . PHP_EOL;
    $html .= 'th, td { padding: 1; }' . PHP_EOL;
    $html .= 'thead, tdata { text-align: left; }' . PHP_EOL;
    $html .= '#spalte_priority { width: 30px; }' . PHP_EOL;
    $html .= '#spalte_floor { width: 15p0x; }' . PHP_EOL;
    $html .= '#spalte_room { width: 250px; }' . PHP_EOL;
    $html .= '#spalte_device { width: 500px; }' . PHP_EOL;
    $html .= '#spalte_state { width: 400px; }' . PHP_EOL;
    $html .= '#spalte_rf { width: 300px; }' . PHP_EOL;
    if ($detailed) {
        $html .= '#spalte_type { width: 200px; }' . PHP_EOL;
        $html .= '#spalte_addr { width: 150px; }' . PHP_EOL;
    }
    $html .= '</style>' . PHP_EOL;

    $html .= '<table>' . PHP_EOL;
    $html .= '<colgroup><col id="spalte_priority"></colgroup>' . PHP_EOL;
    $html .= '<colgroup><col id="spalte_room"></colgroup>' . PHP_EOL;
    $html .= '<colgroup><col id="spalte_device"></colgroup>' . PHP_EOL;
    $html .= '<colgroup><col id="spalte_state"></colgroup>' . PHP_EOL;
    $html .= '<colgroup><col id="spalte_rf"></colgroup>' . PHP_EOL;
    if ($detailed) {
        $html .= '<colgroup><col id="spalte_type"></colgroup>' . PHP_EOL;
        $html .= '<colgroup><col id="spalte_addr"></colgroup>' . PHP_EOL;
    }
    $html .= '<colgroup></colgroup>' . PHP_EOL;

    $lastFloorName = '';
    foreach ($infos as $info) {
        $floorName = $info['floorName'];
        $roomName = $info['roomName'];
        $devName = $info['devName'];

        $mark = [];
        $state = 0;
        if (isset($info['lowbat']) && $info['lowbat']['value']) {
            $mark[] = 'LOWBAT';
            if ($state < 2) {
                $state = 2;
            }
        }
        if (isset($info['config_pending']) && $info['config_pending']['value']) {
            $mark[] = 'CONFIG';
            if ($state < 1) {
                $state = 1;
            }
        }
        if (isset($info['unreach']) && $info['unreach']['value']) {
            $mark[] = 'UNREACH';
            if ($state < 1) {
                $state = 1;
            }
        }
        if (isset($info['duty_cycle']) && $info['duty_cycle']['value']) {
            $mark[] = 'DUTY_CYCLE';
            if ($state < 1) {
                $state = 1;
            }
        }
        if (isset($info['valve_state']) && $info['valve_state']['value']) {
            $mark[] = 'VALVE_STATE';
            if ($state < 1) {
                $state = 1;
            }
        }

        $state_str = $mark != [] ? implode(' ', $mark) : 'ok';
        switch ($state) {
            case 0:
                $state_col = '#32CD32';
                break;
            case 1:
                $state_col = '#FFD700';
                break;
            case 2:
                $state_col = '#FF0000';
                break;
        }

        $type = $info['type'];
        $addr = $info['addr'];

        if ($floorName != $lastFloorName) {
            $html .= '<tr>' . PHP_EOL;
            $html .= '<td colspan=2><b>' . $floorName . '</b></td>' . PHP_EOL;
            $html .= '</tr>' . PHP_EOL;

            $lastFloorName = $floorName;
        }

        $html .= '<tr>' . PHP_EOL;
        $html .= '<td>&nbsp;</td>' . PHP_EOL;
        $html .= '<td>' . $roomName . '</td>' . PHP_EOL;
        $html .= '<td>' . $devName . '</td>' . PHP_EOL;
        $html .= '<td style="color:' . $state_col . '">' . $state_str . '</td>' . PHP_EOL;

        $rf = '';
        /*
        $rssi = $info['rssi_peer']['value'];
        if ($rssi != -65535 && $rssi < 0) {
            $rf .= sprintf('%d dBm ↑&nbsp;&nbsp;', $rssi);
        }
        $rssi = $info['rssi_device']['value'];
        if ($rssi != -65535 && $rssi < 0) {
            $rf .= sprintf('%d dBm ↓&nbsp;&nbsp;', $rssi);
        }
         */
        $html .= '<td>' . $rf . '</td>' . PHP_EOL;

        if ($detailed) {
            $html .= '<td>' . $type . '</td>' . PHP_EOL;
            $html .= '<td>' . $addr . '</td>' . PHP_EOL;
        }

        $html .= '</tr>' . PHP_EOL;
    }
    $html .= '</tdata>' . PHP_EOL;
    $html .= '</table>' . PHP_EOL;

    return $html;
}

function Inventory_UpdateRSSI($delay)
{
    $now = time();
    $n_total = 0;
    $n_updated = 0;
    $n_error = 0;
    $r = Inventory_Check();
    $infos = $r['infos'];
    foreach ($infos as $info) {
        foreach (['rssi_device', 'rssi_peer'] as $s) {
            $v = $info[$s];
            $varID = $v['varId'];
            $n_total++;
            $lastUpdate = $v['lastUpdate'];
            $diff = $now - $lastUpdate;
            if ($lastUpdate > 0 && $diff > $delay && $diff < ($delay * 7)) {
                $parID = IPS_GetParent($varID);
                $dp = strtoupper($s);
                if (@HM_RequestStatus($parID, $dp)) {
                    $n_updated++;
                } else {
                    IPS_LogMessage(__FUNCTION__, 'update datapoint ' . $dp . ' for ' . $info['floorName'] . '\\' . $info['roomName'] . '\\' . $info['devName'] . ' failed');
                    $n_error++;
                }
            }
        }
    }
    IPS_LogMessage(__FUNCTION__, 'total=' . $n_total . ', update=' . $n_updated . ', error=' . $n_error);
}

// LOWBAT, UNREACH, CONFIG_PENDING ...
function Inventory_UpdateDatapoint(string $datapoint, bool $bg = false)
{
    $n_total = 0;
    $n_updated = 0;
    $n_error = 0;
    $r = Inventory_Check();
    $infos = $r['infos'];
    foreach ($infos as $info) {
        if (isset($info['devId']) == false) {
            continue;
        }
        $instID = $info['devId'];
        $varID = @IPS_GetObjectIDByIdent($datapoint, $instID);
        if ($varID == false && $datapoint == 'LOWBAT') {
            $varID = @IPS_GetObjectIDByIdent('LOW_BAT', $instID);
        }
        if ($varID == false && $datapoint == 'DUTY_CYCLE') {
            $varID = @IPS_GetObjectIDByIdent('DUTYCYCLE', $instID);
        }
        if ($varID == false) {
            continue;
        }
        $val = GetValue($varID);
        $n_total++;
        if (@HM_RequestStatus($instID, $datapoint)) {
            if (GetValue($varID) != $val) {
                $n_updated++;
            }
        } else {
            $msg = 'datapoint=' . $datapoint . ' update datapoint ' . $dp . ' for ' . $info['floorName'] . '\\' . $info['roomName'] . '\\' . $info['devName'] . ' failed';
            if ($bg) {
                IPS_LogMessage(__FUNCTION__, $msg, 0);
            } else {
                echo __FUNCTION__ . ': ' . $msg . PHP_EOL;
            }
            $n_error++;
        }
    }
    $msg = 'datapoint=' . $datapoint . ', total=' . $n_total . ', update=' . $n_updated . ', error=' . $n_error;
    if ($bg) {
        IPS_LogMessage(__FUNCTION__, $msg, 0);
    } else {
        echo __FUNCTION__ . ': ' . $msg . PHP_EOL;
    }
}
