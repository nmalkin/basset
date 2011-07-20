<?php
require('includes.php');
require_once('smarty/Smarty.class.php');

// do we have the required parameters?
if(! (isset($_GET['hitId']) && isset($_GET['assignmentId'])) ) {
    die('Missing required parameters');
}

$assignment_id = $_GET['assignmentId'];

try {
    $game = Game::fromHIT($_GET['hitId']);
} catch(DoesNotExistException $e) {
    die('Invalid HIT ID');
} catch(ConfigurationSyntaxException $e) {
    die("<html><pre>Syntax error in game configuration file: " . $e->getMessage() . "\nthrown in:\n" . $e->getTraceAsString()) + "</pre></html>";
}

if($assignment_id == 'ASSIGNMENT_ID_NOT_AVAILABLE') {
    // do something for preview page
    // probably use $game here
} else {
    // are we continuing an existing session?
    try {
        $session = Session::fromAssignmentID($assignment_id, $game);
    } catch(DoesNotExistException $e) {
        // exception was thrown because session for this assignment doesn't exist yet.
        // create it.
        $session = Session::newSession($assignment_id, AMT::getWorkerID($assignment_id), $game);
    }

    switch($session->getStatus()) {
        case Session::finished:
        case Session::terminated:
            $content = 'session ended'; // TODO: better warning?
            break;
        case Session::awaiting_user_input:
            $smarty = new Smarty();
            $basset_variables = new BassetVariables($session);
            $smarty->assign('basset', $basset_variables);
            $content = $smarty->fetch($session->current_step->getHTMLFilename());
            break;
        case Session::group_request_fulfilled:
            // Treat this as if the group request is unfulfilled. 
            // The proper response will occur on the next poll.
        case Session::group_request_pending:
            $content = "waiting for partner"; //TODO: have this match the behavior from driver
            break;
        case Session::finished_step:
        case Session::callback_done;
            $content = 'waiting on partner(s)'; //TODO: have this match the behavior from driver
            break;
        default:
            throw new Exception('unknown session status');
            break;
    }

    
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>BASSET</title>
    <script type="text/javascript" src="jquery.js"></script>
    <script type="text/javascript" src="dotimeout.js"></script>
</head>
<body>
    <div id="countdown"></div>
    <div id="notifications"></div>
    <div id="content">
        <?php echo $content; ?>
    </div>
    <script type="text/javascript">
        var POLL_PERIOD = 2000; // milliseconds
        var sessionID = '<?php echo $session->id; ?>';    
                        
        function dump(obj) {
            var out = '';
            for (var i in obj) {
                out += i + ": " + obj[i] + "\n";
            }

            var pre = document.createElement('pre');
            pre.innerHTML = obj + '\n' + out;
            document.body.appendChild(pre)
        }
        
        /** Posts given data to server. */
        function postToServer(data) {
            $.post(
                'driver.php',
                data,
                processReturnData
            );
        }
        
        /** Processes data returned from server. */
        function processReturnData(data) {
            clearCountdown();
            
            if(data.action == 'wait') {
                // wait: do nothing
            } else if(data.action == 'replace') {
                $('#notifications').html('');
                $('#content').html(data.html); // replace content with given data
                setControls(jQuery.parseJSON(data.controls)); // update controls
            } else if(data.action == 'notify') {
                $('#notifications').html(data.message);
            } else if(data.action == 'countdown') {
                setCountdown(data.seconds);
            } else if(data.action == 'goto') {
                window.location.href = data.href;
            } else {
                // not a known response.
                // assuming an error occurred,
                // we alert with error
                // (also assuming that the response is no longer JSON)
                alert(data);
                // TODO: add a message to try to refresh: this might fix problems
                stopPolling();
            }
        }
        
        /** Sets the countdown */
        function setCountdown(seconds) {
            $('#countdown').html('You have ' + seconds + ' seconds left');
        }
        
        /** Clears or hides the countdown. */
        function clearCountdown() {
            $('#countdown').html('');
        }
        
        /** Polls server repeatedly for further actions. */
        function poll() {
            $.doTimeout('poll', POLL_PERIOD, function(){
                postToServer({ 'sessionID' : sessionID });
                return true; // continue polling
            });
        }
        
        /** Force immediate polling. */
        function pollNow() {
            $.doTimeout('poll', true);
        }
        
        /** Stop polling cycle. */
        function stopPolling() {
            $.doTimeout('poll');
        }
        
        /** Submits the values of all inputs to the server.
         * @param source the id of the button that was clicked. It will be submitted as well.
         */
        function postInputs(source) {
            var data = new Object();
            data['sessionID'] = sessionID;
            data['click'] = source;
            
            for(var i = 0; i < controls.length; i++) {
                var control = controls[i];
                if(control.behavior == 'input') {
                    data[control.id] = $('#' + control.id).val(); 
                }
            }
            
            postToServer(data);
        }
        
        
        /** An array of all the controls for this step. */
        var controls = [];
        
        /** 
         * Sets the controls to the given ones.
         * @param controls an array of objects, each of which has an id and behavior
         */
        function setControls(newControls) {
            if(newControls) {
                clearControls();
                controls = newControls;
                processControls();
            }
        }
        
        /** 
         * Adds appropriate listeners for the controls.
         */
        function processControls() {
            for(var i = 0; i < controls.length; i++) {
                var control = controls[i];
                
                if(control.behavior == 'submit') {
                    var submitFunction = (function(id) {
                                            return function() {
                                                postInputs(id);
                                            }
                                        })(control.id);
                         /* This construct (an immediately evaluated function returning a function) 
                          * is necessitated by the way JavaScript closures work.
                          * See: http://www.mennovanslooten.nl/blog/post/62
                          * alternate: http://www.webcitation.org/5zrnp4adL
                          */
                    $('#' + control.id).click(submitFunction);
                }
            }
        }
        
        /** Removes any listeners that were added to the controls. */
        function clearControls() {
            for(var i = 0; i < controls.length; i++) {
                var control = controls[i];
                
                if(control.behavior == 'submit') {
                    $('#' + control.id).unbind();
                }
            }
        }
        
        $(document).ready(function(){
            poll(); // start poll cycle
            pollNow(); 
            
            setControls(<?php echo json_encode($session->current_step->getControls()); ?>);
        });
    </script>
    <p><input type="button" id="freeze" value="Freeze" /></p>
    <script type="text/javascript">
        $('#freeze').click(function() {stopPolling();});
    </script>
    <noscript>
        <p>This application requires JavaScript to run, but your browser does not appear to have it, or it is disabled.</p>
        <ul>
            <li>If you have disabled JavaScript in your browser, <a href="http://www.google.com/support/websearch/bin/answer.py?hl=en&answer=23852">enable it</a> before accepting this HIT.</li>
            <li>If your browser does not support JavaScript, don't accept this HIT; you won't be able to complete it.</li>
            <li>If your browser does not support JavaScript, and you have accepted this HIT, please return it; you won't be able to complete it.</li>
        </ul>
        </noscript>
</body>
</html>