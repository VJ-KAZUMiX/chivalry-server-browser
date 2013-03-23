<?php
/**
 * Created by JetBrains PhpStorm.
 * User: KAZUMiX
 * Date: 13/03/17
 * Time: 17:48
 * To change this template use File | Settings | File Templates.
 */

require_once "steam-condenser-php/lib/steam-condenser.php";
require_once "GeoIP/php-1.12/geoip.inc";

// error handler
function myErrorHandler($errno, $errstr, $errfile, $errline)
{
    if ($errno == E_USER_NOTICE) {
        return true;
    }
    return false;
}
// set error handler
$old_error_handler = set_error_handler("myErrorHandler");

$masterServer = new MasterServer(MasterServer::SOURCE_MASTER_SERVER);
//$serverArray = $masterServer->getServers(MasterServer::REGION_ALL, "\\type\\d\\empty\\1\\full\\1\\gamedir\\tf");
$serverArray = $masterServer->getServers(MasterServer::REGION_ALL, "\\type\\d\\gamedir\\chivalrymedievalwarfare");

//var_dump($masterServer);


// throw exception test
$server = new GoldSrcServer('192.168.1.192', 80);
try {
    $server->initialize();
    $players = $server->getPlayers();
    $serverInfo = $server->getServerInfo();
    var_dump($serverInfo);
} catch (Exception $e) {
    var_dump($e);
}

$gi = geoip_open("GeoIP/GeoIP.dat",GEOIP_STANDARD);
$countryList = array();

foreach ($serverArray as $server) {
    $ipAddress = $server[0];
    $portNo = $server[1];
    $countryCode = geoip_country_code_by_addr($gi, $ipAddress);
    if (!isset($countryList[$countryCode])) {
        $countryList[$countryCode] = array();
    }
    $serverDic = array();
    $serverDic['ipAddress'] = $ipAddress;
    $serverDic['portNo'] = $portNo;
    $countryList[$countryCode][] = $serverDic;

    if ($countryCode == 'JP' || $countryCode == 'KR') {
        $server = new GoldSrcServer($ipAddress, $portNo);
        $server->initialize();
        $players = $server->getPlayers();
        $serverInfo = $server->getServerInfo();
        var_dump($serverInfo);
        var_dump($players);
    }
}

var_dump($countryList);

echo 'ok';