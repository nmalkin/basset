<?php
//TODO: autoload?
require_once('amt.php');
require_once('bassetvariables.php');
require_once('constants.php');
require_once('control.php');
require_once('database.php');
require_once('game.php');
require_once('group.php');
require_once('grouprequest.php');
require_once('grouprequestqueue.php');
require_once('round.php');
require_once('session.php');
require_once('step.php');

class DoesNotExistException extends Exception {}
class ConfigurationSyntaxException extends Exception {}
?>