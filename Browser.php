<?php
/**
 * Created by IntelliJ IDEA.
 * User: KAZUMiX
 * Date: 13/04/21
 * Time: 17:54
 * To change this template use File | Settings | File Templates.
 */

require_once 'config.php';
require_once 'GameServerManager.php';
require_once 'MySmarty.php';

class Browser {

    /**
     * @var GameServerManager
     */
    private $gameServerManager;

    private $mySmarty;

    public $countryCodeAssoc;

    public $targetCountryCode = null;
    public $serverList = null;
    public $serverInfo = null;

    public function __construct() {
        $this->gameServerManager = GameServerManager::sharedManager();
        $this->mySmarty = new MySmarty();
        $this->countryCodeAssoc = get_country_assoc();

        if (isset($_GET['serverId'])) {
            $this->viewServerInfo($_GET['serverId']);
        } else {
            $countryCodeArray = $this->getTargetCountryCodeArray();
            $this->viewServerList($countryCodeArray);
        }

    }

    private function getTargetCountryCodeArray() {
        if (isset($_GET['country']) && isset($this->countryCodeAssoc[$_GET['country']])) {
            $this->targetCountryCode = $_GET['country'];
            return array($_GET['country']);
        }

        $countryCode = geoip_country_code_by_addr(GameServerManager::getGeoIp(), $_SERVER['REMOTE_ADDR']);
        if (!$countryCode) {
            $countryCode = 'US';
        }

        $this->targetCountryCode = $countryCode;
        return array($countryCode);
    }

    private function viewServerList($countryCodeArray) {
        $this->serverList = $this->gameServerManager->getServerList($countryCodeArray);
        $this->mySmarty->assign('data', $this);
        $this->mySmarty->display('list.html');
    }

    private function viewServerInfo($serverId) {
        $this->serverInfo = $this->gameServerManager->getServerInfo($serverId);
        $this->mySmarty->assign('data', $this);
        $this->mySmarty->display('list.html');
    }

}