<?php

namespace SimpleSAML\Module\totara;

use SimpleSAML\Configuration;
use SimpleSAML\Session;
use Symfony\Component\HttpFoundation\Request;

$config = Configuration::getInstance();
$session = Session::getSessionFromRequest();
$request = Request::createFromGlobals();

$controller = new ManageMetadataController($config, $session);
$controller->main($request)->send();
