<?php
require('includes.php');
require_once('smarty/Smarty.class.php');

$RESPONSE = NULL;
$SESSION = NULL;

function getResponse() {
    global $RESPONSE, $SESSION;
    
    if(! isset($_POST['sessionID'])) {
        $RESPONSE = 'missing session ID';
        return;
    }
    
    try {
        $SESSION = Session::fromSessionID($_POST['sessionID']);
    } catch(DoesNotExistException $e) {
        $RESPONSE = 'invalid session ID';
        return;
    }
    
    switch($SESSION->getStatus()) {
        case Session::awaiting_user_input:
            processInput();
            break;
        case Session::group_request_pending:
            // assuming a request is pending in the queue, has it expired?
            if($request = GroupRequestQueue::retrieveRequest($SESSION)) {
                if($request->expired()) {
                    // okay, we're done with this request
                    // delete it from the database
                    GroupRequestQueue::deleteRequests(array($request));
                    
                    $RESPONSE = 'no partner found! exiting. (TODO: exit trail)'; //TODO: exit trail
                    return;
                }
            }
            
            // nothing to do, except wait, because if a suitable partner comes along, the request pending status will be changed
            $RESPONSE = array('action' => 'wait'); // TODO: countdown?
            break;
        case Session::group_request_fulfilled:
            startStep();
            break;
        case Session::finished_step:
            if(readyToMoveOn()) {
                executeUserCallback();
                advanceStep();
            } else {
                /* 
                 * readyToMoveOn sets the RESPONSE to the HTML of the waiting file.
                 * But because their status was already finished_step,
                 * we can assume that the waiting screen has already been loaded.
                 * So we just tell them to wait (overriding the response).
                */
//                $RESPONSE = array('action' => 'wait');
             }
            break;
        case Session::finished:
        case Session::terminated:
            $RESPONSE = 'session ended';
            break;
        default:
            throw new Exception('unknown session status');
            break;
    }
}

/**
 * Check for input.
 * If there is none, return waiting signal.
 * If there is input, validate it, and either report errors or move on to next step.
 */
function processInput() {
    global $RESPONSE, $SESSION;
    
    if(sessionExpired()) return;

    // did we get the information necessary to move on to the next step?
    if(isset($_POST['click'])) { // data was submitted
        if(! validateInput()) return;
        storeInput();
        $SESSION->setStatus(Session::finished_step);
        
        if(readyToMoveOn()) {
            executeUserCallback();
            advanceStep();
        }
    } else { // no user input
        if($SESSION->current_step->time_limit > 0) { // if there is a time limit, announce remaining time
            $RESPONSE = array('action' => 'countdown', 'seconds' => $SESSION->expires - time());
        } else { // tell client to wait
            $RESPONSE = array('action' => 'wait');
        }
    }
}

/**
 * Are we ready to move on to the next step?
 * Yes, if all my partners are done.
 * 
 * @return boolean TRUE if ready
 */
function readyToMoveOn() {
    global $RESPONSE, $SESSION;
    
    if($SESSION->current_step->requiresGroup()) {
        $group = $SESSION->getCurrentGroup();
        
        if(! $group->allAlive()) {
            $RESPONSE = 'group expired. TODO: exit trail'; //TODO: exit trail
            return FALSE;
        }
        
        if(! $group->finishedRound($SESSION->currentRound())) { // waiting for partners' input
            $RESPONSE = array('action' => 'replace', 'html' => 'waiting on partner(s)', 'controls' => array()); //TODO: better waiting screen (load from file)
            return FALSE;
        }
    }
    
    return TRUE;
}

/**
 * Time limit enforcement: checks if the current session is expired.
 * 
 * @return boolean TRUE if the session is expired, FALSE otherwise
 */
function sessionExpired() {
    global $RESPONSE, $SESSION;
    
    if($SESSION->expired()) { // time has run out!
        if($SESSION->game->timeout_behavior == Game::terminate) {
            $SESSION->terminateSession();
            $RESPONSE = 'session terminated! (TODO: redirect)';
            return TRUE;
        } elseif($SESSION->game->timeout_behavior == Game::skip) {
            $RESPONSE = 'TODO: timeout behavior = skip';
            return TRUE;
        }
    }
    
    return FALSE;
}

/**
 * Validates data submitted by the user, 
 * building an array with user-submitted values as it goes.
 * 
 * @return boolean TRUE if the user data is valid, FALSE if it isn't
 */
function validateInput() {
    global $RESPONSE, $SESSION;
    
    // did they click on one of the available buttons?
    $known_button = array_reduce($SESSION->current_step->controls, function($v, $w) {
        return $v || ( $w->behavior == Control::button && $w->id == $_POST['click'] );
    }, FALSE);

    if(! $known_button) { // No, they didn't click on a known button. What the hell did they click, then?
            $RESPONSE = 'unknown submission trigger: ' . $_POST['click'];
            return FALSE;
    }
    
    // validate inputs
    foreach($SESSION->current_step->controls as $control) { // for each input:
        if($control->behavior == Control::input) {
            // check that we have a value for it
            if(! isset($_POST[$control->id])) {
                    // we are missing a value for this input.
                    // we therefore ignore this entire request.
                    $RESPONSE = array('action' => 'notify', 'message' => 'missing value for element ' . $control->id);
                    return FALSE;
            }

            // check that the value passes validation
            if(! $control->validate($_POST[$control->id])) {
                    $RESPONSE = array('action' => 'notify', 'message' => $control->id . ' failed validation (TODO: better message)'); // TODO: have descriptive names for controls?
                    return FALSE;
            }
        }
    }
    
    return TRUE;
}

/**
 * Stores user input for the current step.
 */
function storeInput() {
    global $SESSION;
    
    $step_data = &$SESSION->currentRoundData();
    
    $step_data['click'] = $_POST['click'];
    
    foreach($SESSION->current_step->controls as $control) { // for each input:
        if($control->behavior == Control::input) {
            $step_data[$control->id] = $_POST[$control->id];
        }
    }
}

function executeUserCallback() {
    global $RESPONSE, $SESSION;
    
    // construct the data object that will be passed to the user
    $basset_variables = new BassetVariables($SESSION);

    // prepare and execute user-defined callback
    if($SESSION->current_step->on_complete) {
        // the submission data from the latest round will also be passed to the user
        $input_data = &$SESSION->currentRoundData(); 
        $submit_variables = (object) $input_data; // ... as an object
        // include the function file
        $function_file = $SESSION->current_step->game->directory . Game::function_file;
        if(! file_exists($function_file)) {
            $RESPONSE = "ERROR: game missing function file (looked for $function_file)"; //TODO: throw exception?
            return;
        }
        include $function_file;
        // get the name of the callback function
        $user_function = $SESSION->current_step->on_complete;
        // check that the function exists
        if(! function_exists($user_function)) { throw new ConfigurationSyntaxException("callback function $user_function does not exist"); }
        // execute the callback
        $user_function($basset_variables, $submit_variables);
        // save variables after callback
        $basset_variables->save();
    }
    
    // NOTE: in partner rounds, callbacks are executed after all users have completed the step
}

/** advance session to the next step (or repetition) */
function advanceStep() {
    global $RESPONSE, $SESSION;
    
    try {
        $SESSION->advance();
    } catch(DoesNotExistException $e) {
        $SESSION->endSession();
        $RESPONSE = "You're done!";//TODO: redirect to AMT
        return;
    }
    
    if(groupAvailable()) { // group formed, can request user input
        startStep();
    }
}

function groupAvailable() {
    global $RESPONSE, $SESSION;
    
    switch($SESSION->current_step->group) {
        case Step::group_new:
            // try getting a new group
            if(Group::getNewGroup($SESSION)) {
                return TRUE;
            } else { // no group available: wait for partner
                $RESPONSE = array('action' => 'replace', 'html' => 'waiting for partner(s)', 'controls' => array()); //TODO: better waiting screen (load from file)
                return FALSE;
            }
            break;
        case Step::group_keep:
            if(Group::getOldGroup($SESSION)) {
                return TRUE;
            } else { // no group available: wait for partner
                $RESPONSE = array('action' => 'replace', 'html' => 'waiting on partner(s)', 'controls' => array()); //TODO: better waiting screen (load from file)
                return FALSE;
            }
            break;
        case Step::group_unique:
            throw new Exception("TODO");//TODO: implement
        case Step::group_none:
        default:
            return TRUE;
            break;
    }
}

function startStep() {
    global $RESPONSE, $SESSION;
    
    $SESSION->startStep();

    $basset_variables = new BassetVariables($SESSION); // TODO: rather than constructing it anew, get the one created by callback (?)

    // initialize Smarty template engine
    $smarty = new Smarty();
//    $smarty->registerObject('basset', $basset_variables);
    $smarty->assign('basset', $basset_variables);

    $RESPONSE = array(
        'action' => 'replace',
        'html' => $smarty->fetch($SESSION->current_step->getHTMLFilename()),
        'controls' => json_encode($SESSION->current_step->getControls())
    );
}

/******************************************************************************/

getResponse();

if(is_array($RESPONSE)) {
    header("Content-type: application/json");
    echo json_encode($RESPONSE);
} else {
    echo $RESPONSE;
}