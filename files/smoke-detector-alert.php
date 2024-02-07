<?php

declare(strict_types=1);

require_once IPS_GetScriptFile(GetLocalConfig('INVENTORY_HELPER'));

$scriptName = IPS_GetName($_IPS['SELF']) . '(' . $_IPS['SELF'] . ')';
$scriptInfo = IPS_GetName(IPS_GetParent($_IPS['SELF'])) . '\\' . IPS_GetName($_IPS['SELF']);

$varID = $_IPS['VARIABLE'];
$parID = IPS_GetParent(IPS_GetParent($varID));
$state = '';
$obj = IPS_GetObject($varID);
switch ($obj['ObjectIdent']) {
    case 'SMOKE_DETECTOR_ALARM_STATUS':
        if (GetValueInteger($varID) == 1 /* Alarm ausgelöst */) {
            $state = 'Rauchmelder (' . IPS_GetName($parID) . '): ' . GetValueFormatted($varID);
        }
        break;
    default:
        break;
}

$msg = '';
if ($state) {
    $catID = GetLocalConfig('Geräte-Status');
    $varID = Variable_Create($catID, '', 'Rauchmelder ausgelöst', VARIABLETYPE_BOOLEAN, 'Local.JaNein', 0, 0);
    Notice_TriggerRule(GetLocalConfig('NOTICE_RULE_ALERT'), $state, '', 'alert', []);
    $msg = ' => ausgelöst';
}

IPS_LogMessage($scriptName, $scriptInfo . ': state=' . $state . $msg);
