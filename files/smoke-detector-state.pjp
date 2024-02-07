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
    case 'ERROR_DEGRADED_CHAMBER':
        if (GetValueBoolean($varID) != true /* Rauchkammer verschmutzt */) {
            $state = 'Rauchmelder (' . IPS_GetName($parID) . '): ' . GetValueFormatted($varID);
        }
        break;
    default:
        break;
}

$msg = '';
if ($state) {
    Notice_TriggerRule(GetLocalConfig('NOTICE_RULE_WARNING'), $state, '', 'alert', []);
    $msg = ' => gemeldet';
}

IPS_LogMessage($scriptName, $scriptInfo . ': state=' . $state . $msg);
