<?php
/**
 * Created by IntelliJ IDEA.
 * User: KAZUMiX
 * Date: 13/04/15
 * Time: 0:05
 * To change this template use File | Settings | File Templates.
 */

/*
 * execute this script every 1 minute as a cron job
 */

require_once 'config.php';
require_once 'GameServerManager.php';

// making the php path for the fputs
$fputsPhpPath = HTTP_SERVER_PATH . 'updateTargetServer.php';

$gameServerManager = GameServerManager::sharedManager();
$gameServerManager->deleteUnrespondedServers(5);

// update all servers every 5 minutes
$sec = time();
$min = floor($sec / 60);
if ($min % 5 === 0) {
    $gameServerManager->updateWithMasterServer();
    $serverList = $gameServerManager->getServerList();
} else {
    $serverList = $gameServerManager->getServerList(array('JP', 'KR'));
}

// update each server asyncronous
foreach ($serverList as $serverRecord) {
    $gameServerId = $serverRecord['game_server_id'];
    $fp = fsockopen (HTTP_SERVER_NAME, HTTP_SERVER_PORT, $errNo, $errStr, 5);
    if (!$fp) {
        echo "Error: $errStr ($errNo)<br>\n";
    } else {
        socket_set_blocking($fp, false);
        fputs ($fp, "GET {$fputsPhpPath}?serverId={$gameServerId} HTTP/1.0\r\n\r\n");
        fclose ($fp);
        echo "Current time: " . time() . "<br>\n";
    }
    usleep(5 * 1000);
}

