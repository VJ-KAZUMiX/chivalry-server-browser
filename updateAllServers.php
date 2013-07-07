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
//$gameServerManager->deleteUnrespondedServers(5);

// update all servers every 12 minutes
// TODO: improve
$sec = time();
$min = floor($sec / 60);
if ($min % 12 === 0 || isset($_GET['force'])) {
    // Delete servers unresponded for 2 dayes
    $gameServerManager->deleteUnrespondedServersWithTime( 60 * 60 * 24 * 2 );
    $gameServerManager->updateWithMasterServer();
    $serverList = $gameServerManager->getServerList();
} else {
    $serverList = $gameServerManager->getServerList(array('JP'));
}

// insert statistics
if ($min % 12 === 1 || isset($_GET['statistics'])) {
    $gameServerManager->insertCountryPlayers();
}

$numberOfIdSet = ceil(count($serverList) / 30);
$argSetArray = array();
for ($i=0; $i<$numberOfIdSet; $i++) {
    $argSetArray[] = array();
}

$counter = 0;
foreach ($serverList as $serverRecord) {
    $gameServerId = $serverRecord['game_server_id'];
    $argSetArray[$counter][] = $gameServerId;
    $counter++;
    $counter %= $numberOfIdSet;
}

foreach ($argSetArray as $argSet) {
    if (!count($argSet)) {
        break;
    }

    $arg = implode(' ', $argSet);

    if (PHP_OS !== 'WIN32' && PHP_OS !== 'WINNT') {
        // making exec path
        $command = EXEC_PHP . " {$arg} > /dev/null &";
        exec($command);
    }
    else {
        $command = EXEC_PHP . " {$arg}";
        $fp = popen('start /B ' . $command, 'r');
        pclose($fp);
    }

    echo "Current time: " . time() . " [{$command}]<br />\n";
    usleep(20 * 1000);

}
