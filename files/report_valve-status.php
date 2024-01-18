<?php

declare(strict_types=1);

require_once IPS_GetScriptFile(GetLocalConfig('INVENTORY_HELPER'));

$scriptName = IPS_GetName($_IPS['SELF']) . '(' . $_IPS['SELF'] . ')';
$scriptInfo = IPS_GetName(IPS_GetParent($_IPS['SELF'])) . '\\' . IPS_GetName($_IPS['SELF']);

$old_count = isset($_IPS['OLDVALUE']) ? $_IPS['OLDVALUE'] : '';
$count = GetValueInteger($_IPS['VARIABLE']);

$msg = '';

if ($count > $old_count) {
    /*
    $txt = 'Ventil-Alarm bei ' . $count . ' Gerät(en)';
    Notice_TriggerRule(GetLocalConfig('NOTICE_RULE_SYSADM'), $txt, '', 'notice', []);
     */

    $infoList = Inventory_Check();
    $infos = $infoList['infos'];
    foreach ($infos as $info) {
        if (!(isset($info['valve_state']) && $info['valve_state']['value'])) {
            continue;
        }
        $roomName = $info['roomName'];
        $devName = $info['devName'];
        $txt = 'Ventil-Alarm: ' . $roomName . '\\' . $devName;
        Notice_Log(GetNoticeBase(), $txt, 'warn', []);
        Notice_TriggerRule(GetLocalConfig('NOTICE_RULE_SYSADM'), $txt, '', 'notice', []);
    }

    $msg = 'sent notification' . PHP_EOL;
}

IPS_LogMessage($scriptName, $scriptInfo . ': count=' . $count . '(prev=' . $old_count . ') => ' . $msg);
