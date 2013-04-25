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

$gameServerIdArray = array();
if (isset($_GET['serverIds'])) {
    $gameServerIdArray = explode(',', $_GET['serverIds']);
} else {
    for ($i=1; $i<$argc; $i++) {
        $gameServerId = intval($argv[$i]);
        if ($gameServerId) {
            $gameServerIdArray[] = $gameServerId;
        }
    }
}

$gameServerManager = GameServerManager::sharedManager();
foreach ($gameServerIdArray as $gameServerId) {
    $gameServerManager->updateTargetServerInfo($gameServerId);
}
