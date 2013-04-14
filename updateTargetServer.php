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

$gameServerId = isset($_GET['serverId']) ? $_GET['serverId'] : 0;
if ($gameServerId === 0) {
    exit();
}

$gameServerManager = GameServerManager::sharedManager();
$gameServerManager->updateTargetServerInfo($gameServerId);
