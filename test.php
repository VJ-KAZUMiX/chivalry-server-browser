<?php
/**
 * Created by JetBrains PhpStorm.
 * User: KAZUMiX
 * Date: 13/03/06
 * Time: 0:56
 * To change this template use File | Settings | File Templates.
 */

//phpinfo();

require_once 'GameServerManager.php';

class TestManager {

    public function __construct() {

    }

    public function test() {
        $gameServerManager = GameServerManager::sharedManager();

        for ($i=0; $i<100; $i++) {
            $gameServerManager->addGameServer(rand(1,254) . '.' . rand(1,254) . '.' . rand(1,254) . '.' . rand(1,254), rand(0, 100));
        }
    }
}

$testManager = new TestManager();
$testManager->test();
