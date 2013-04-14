<?php
/**
 * Created by IntelliJ IDEA.
 * User: KAZUMiX
 * Date: 13/04/15
 * Time: 0:05
 * To change this template use File | Settings | File Templates.
 */

require_once 'config.php';
require_once 'GameServerManager.php';

$gameServerManager = GameServerManager::sharedManager();
//$serverList = $gameServerManager->getServerList(array('JP', 'KR'));
$serverList = $gameServerManager->getServerList();

foreach ($serverList as $serverRecord) {
    $gameServerId = $serverRecord['game_server_id'];
    $fp = fsockopen ($_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $errNo, $errStr, 5);
    if (!$fp) {
        echo "Error: $errStr ($errNo)<br>\n";
    } else {
        socket_set_blocking($fp, false);
        fputs ($fp, "GET /updateTargetServer.php?serverId={$gameServerId} HTTP/1.0\r\n\r\n");
        fclose ($fp);
        echo "Current time: " . time() . "<br>\r";
    }
    usleep(5 * 1000);
}

