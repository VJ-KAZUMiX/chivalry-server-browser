<?php
/**
 * Created by IntelliJ IDEA.
 * User: KAZUMiX
 * Date: 13/04/14
 * Time: 23:46
 * To change this template use File | Settings | File Templates.
 */

ignore_user_abort(TRUE);
require_once 'config.php';
require_once 'GameServerManager.php';

$gameServerId = isset($_GET['serverId']) ? intval($_GET['serverId']) : 0;
if ($gameServerId === 0) {
    $gameServerId = isset($argv[1]) ? intval($argv[1]) : 0;

    if ($gameServerId === 0) {
        echo 'exit';
        exit();
    }
}

$gameServerManager = GameServerManager::sharedManager();
$gameServerManager->updateTargetServerInfo($gameServerId);
