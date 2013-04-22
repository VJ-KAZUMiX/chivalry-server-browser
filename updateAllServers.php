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
if ($min % 15 === 0 || isset($_GET['force'])) {
    $gameServerManager->updateWithMasterServer();
    $serverList = $gameServerManager->getServerList();
} else {
    $serverList = $gameServerManager->getServerList(array('JP'));
}

foreach ($serverList as $serverRecord) {
    $gameServerId = $serverRecord['game_server_id'];

    if (PHP_OS !== 'WIN32' && PHP_OS !== 'WINNT') {
        // making exec path
        $command = EXEC_PHP . " {$gameServerId} > /dev/null &";
        exec($command);
    }
    else {
        $command = EXEC_PHP . " {$gameServerId}";
        $fp = popen('start /B ' . $command, 'r');
        pclose($fp);
    }

    echo "Current time: " . time() . " [{$command}]<br />\n";
    usleep(5 * 1000);
}

