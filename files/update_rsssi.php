<?php

declare(strict_types=1);

require_once IPS_GetScriptFile(GetLocalConfig('INVENTORY_HELPER'));

Inventory_UpdateRSSI(3600 * 24);
