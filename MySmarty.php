<?php
/**
 * Created by IntelliJ IDEA.
 * User: KAZUMiX
 * Date: 13/04/21
 * Time: 17:35
 * To change this template use File | Settings | File Templates.
 */

require_once 'Smarty/libs/Smarty.class.php';

class MySmarty extends Smarty {
    public function __construct() {
        parent::__construct();
        $this->setTemplateDir('./templates_smarty/');
        $this->setCompileDir('./templates_smarty_cache');
    }
}