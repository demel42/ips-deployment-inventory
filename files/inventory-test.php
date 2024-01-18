<?php

declare(strict_types=1);

require_once IPS_GetScriptFile(GetLocalConfig('INVENTORY_HELPER'));

$msg = '';

$infoList = Inventory_Check();
$infos = $infoList['infos'];
foreach ($infos as $info) {
    echo print_r($info, true) . PHP_EOL;
    if (!(isset($info['lowbat']) && $info['lowbat']['value'])) {
        continue;
    }

    $floorName = $info['floorName'];
    $roomName = $info['roomName'];
    $devName = $info['devName'];
    $msg .= $floorName . '\\' . $roomName . '\\' . $devName . PHP_EOL;
}

if ($msg != '') {
    echo $msg;
}