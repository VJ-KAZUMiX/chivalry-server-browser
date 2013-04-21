<?php
/**
 * Created by IntelliJ IDEA.
 * User: KAZUMiX
 * Date: 13/04/15
 * Time: 0:05
 * To change this template use File | Settings | File Templates.
 */

/*
 * execute this script every 1 minutes as a cron job
 */

require_once 'config.php';
require_once 'GameServerManager.php';

$gameServerManager = GameServerManager::sharedManager();
$gameServerManager->deleteUnrespondedServers(5);

// update all servers every 10 minutes
// TODO: improve
$sec = time();
$min = floor($sec / 60);
if ($min % 15 === 0) {
    $gameServerManager->updateWithMasterServer();
    $serverList = $gameServerManager->getServerList();
} else {
    $serverList = $gameServerManager->getServerList(array('JP'));
}
// if the server list is empty, update all
if (!count($serverList)) {
    $gameServerManager->updateWithMasterServer();
    $serverList = $gameServerManager->getServerList();
}

if (USE_EXEC) {
    foreach ($serverList as $serverRecord) {
        $gameServerId = $serverRecord['game_server_id'];
        // making exec path
        //$execPath = EXEC_PHP . " {$gameServerId}";
        $execPath = EXEC_PHP . " {$gameServerId} > /dev/null &";
        exec($execPath);
        echo "Current time: " . time() . " [{$execPath}]<br />\n";
        usleep(5 * 1000);
    }
} else {
    // making the php path for the fputs
    $fputsPhpPath = HTTP_SERVER_PATH . 'updateTargetServer.php';
    // update each server asyncronous
    foreach ($serverList as $serverRecord) {
        $gameServerId = $serverRecord['game_server_id'];
        $fp = fsockopen (HTTP_SERVER_NAME, HTTP_SERVER_PORT, $errNo, $errStr, 5);
        if (!$fp) {
            echo "Error: $errStr ($errNo)<br>\n";
        } else {
            socket_set_blocking($fp, false);
            fputs ($fp, "GET {$fputsPhpPath}?serverId={$gameServerId} HTTP/1.0\r\n\r\n");
            /* for debug
            while (!feof($fp)) {
                echo fgets($fp, 128);
            }
            */
            echo "Current time: " . time() . " [GET {$fputsPhpPath}?serverId={$gameServerId}]<br />\n";
        }
        fclose ($fp);
        usleep(5 * 1000);
    }
}

