<?php

include_once __DIR__ . '/../autoload.php';


error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

$temp = \tmwe_email\rabbitmq\email\Email_Consumer::get_instance();
$temp->consume();
