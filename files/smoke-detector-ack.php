<?php

declare(strict_types=1);

require_once IPS_GetScriptFile(GetLocalConfig('GLOBAL_HELPER'));

$scriptName = IPS_GetName($_IPS['SELF']) . '(' . $_IPS['SELF'] . ')';
$scriptInfo = IPS_GetName(IPS_GetParent($_IPS['SELF'])) . '\\' . IPS_GetName($_IPS['SELF']);

$catID = GetLocalConfig('Geräte-Status');
$varID = Variable_Create($catID, '', 'Rauchmelder ausgelöst', VARIABLETYPE_BOOLEAN, 'Local.JaNein', 0, 0);
$smokeDetected = GetValueBoolean($varID);

$msg = '';
if ($smokeDetected) {
    SetValueBoolean($varID, false);
    Notice_TriggerRule(GetLocalConfig('NOTICE_RULE_ALERT'), 'Die Auslösung des Rauchmelders wurde quittiert', '', 'info', []);
    $msg = ' => quittiert';
}

IPS_LogMessage($scriptName, $scriptInfo . ': smokeDetected=' . bool2str($smokeDetected) . ' => ' . $msg);
