<?php

namespace App\Compatibility\Asterisk;

use App\Compatibility\Asterisk\Agi;
use App\Compatibility\Asterisk;

define('AST_CONFIG_DIR', '/etc/asterisk/');
define('AST_SPOOL_DIR', '/var/spool/asterisk/');
define('AST_TMP_DIR', AST_SPOOL_DIR . '/tmp/');
define('DEFAULT_PHPAGI_CONFIG', AST_CONFIG_DIR . '/phpagi.conf');

define('AST_DIGIT_ANY', '0123456789#*');

define('AGIRES_OK', 200);

define('AST_STATE_DOWN', 0);
define('AST_STATE_RESERVED', 1);
define('AST_STATE_OFFHOOK', 2);
define('AST_STATE_DIALING', 3);
define('AST_STATE_RING', 4);
define('AST_STATE_RINGING', 5);
define('AST_STATE_UP', 6);
define('AST_STATE_BUSY', 7);
define('AST_STATE_DIALING_OFFHOOK', 8);
define('AST_STATE_PRERING', 9);

define('AUDIO_FILENO', 3); // STDERR_FILENO + 1

class AsteriskManager
{
  public $config;
	public $socket = NULL;
	public $server;
	public $port;
	private $pagi;
	private $event_handlers;
	private $_logged_in = FALSE;
	
	
	public function __construct($config=[])
	{
    // add default values to config for uninitialized values
    $this->config['asmanager']['server'] = $config['asmanager']['server'];
    $this->config['asmanager']['port'] = 5038;
    $this->config['asmanager']['username'] = $config['asmanager']['username'];
    $this->config['asmanager']['secret'] = $config['asmanager']['secret'];
  }
    /**
	 * Send a request
	 *
	 * @param [type] $action
	 * @param array $parameters
	 * @return void
	 */
    function send_request($action, $parameters=array())
    {
      $req = "Action: $action\r\n";
      foreach($parameters as $var=>$val)
        $req .= "$var: $val\r\n";
      $req .= "\r\n";
      fwrite($this->socket, $req);
      return $this->wait_response();
    }

   	/**
     * Wait for a response
     *
     * If a request was just sent, this will return the response.
     * Otherwise, it will loop forever, handling events.
     *
     * @param boolean $allow_timeout if the socket times out, return an empty array
     * @return array of parameters, empty on timeout
     */
    function wait_response($allow_timeout=false)
    {
      $timeout = false;
      do
      {
        $type = NULL;
        $parameters = array();

        $buffer = trim(fgets($this->socket, 4096));
        while($buffer != '')
        {
          $a = strpos($buffer, ':');
          if($a)
          {
            if(!count($parameters)) // first line in a response?
            {
              $type = strtolower(substr($buffer, 0, $a));
              if(substr($buffer, $a + 2) == 'Follows')
              {
                // A follows response means there is a miltiline field that follows.
                $parameters['data'] = '';
                $buff = fgets($this->socket, 4096);
                while(substr($buff, 0, 6) != '--END ')
                {
                  $parameters['data'] .= $buff;
                  $buff = fgets($this->socket, 4096);
                }
              }
            }

            // store parameter in $parameters
            $parameters[substr($buffer, 0, $a)] = substr($buffer, $a + 2);
          }
          $buffer = trim(fgets($this->socket, 4096));
        }

        // process response
        switch($type)
        {
          case '': // timeout occured
            $timeout = $allow_timeout;
            break;
          case 'event':
            $this->process_event($parameters);
            break;
          case 'response':
            break;
          default:
            $this->log('Unhandled response packet from Manager: ' . print_r($parameters, true));
            break;
        }
      } while($type != 'response' && !$timeout);
      return $parameters;
    }

   /**
    * Connect to Asterisk
    *
    * @example examples/sip_show_peer.php Get information about a sip peer
    *
    * @param string $server
    * @param string $username
    * @param string $secret
    * @return boolean true on success
    */
    function connect($server=NULL, $username=NULL, $secret=NULL)
    {
      // use config if not specified
      if(is_null($server)) $server = $this->config['asmanager']['server'];
      if(is_null($username)) $username = $this->config['asmanager']['username'];
      if(is_null($secret)) $secret = $this->config['asmanager']['secret'];
      $this->config['asmanager']['port']='5038';
      // get port from server if specified
      if(strpos($server, ':') !== false)
      {
        $c = explode(':', $server);
        $this->server = $c[0];
        $this->port = $c[1];
      }
      else
      {
        $this->server = $server;
        $this->port = $this->config['asmanager']['port'];
      }

      // connect the socket
      $errno = $errstr = NULL;
      $this->socket = @fsockopen($this->server, $this->port, $errno, $errstr);
      if($this->socket == false)
      {
      	dd("Unable to connect to manager {$this->server}:{$this->port} ($errno): $errstr");
        $this->log("Unable to connect to manager {$this->server}:{$this->port} ($errno): $errstr");
        return false;
      }

      // read the header
      $str = fgets($this->socket);
      if($str == false)
      {
        // a problem.
        $this->log("Asterisk Manager header not received.");
        return false;
      }
      else
      {
        // note: don't $this->log($str) until someone looks to see why it mangles the logging
      }

      // login
      $res = $this->send_request('login', array('Username'=>$username, 'Secret'=>$secret));
      if($res['Response'] != 'Success')
      {
      	dd($res['Response']);
        $this->_logged_in = FALSE;
        $this->log("Failed to login.");
        $this->disconnect();
        return false;
      }
      $this->_logged_in = TRUE;
      return true;
    }

   /**
    * Disconnect
    *
    * @example examples/sip_show_peer.php Get information about a sip peer
    */
    function disconnect()
    {
      if($this->_logged_in==TRUE)
        $this->logoff();
      fclose($this->socket);
    }

   // *********************************************************************************************************
   // **                       COMMANDS                                                                      **
   // *********************************************************************************************************

   /**
    * Set Absolute Timeout
    *
    * Hangup a channel after a certain time.
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+AbsoluteTimeout
    * @param string $channel Channel name to hangup
    * @param integer $timeout Maximum duration of the call (sec)
    */
    function AbsoluteTimeout($channel, $timeout)
    {
      return $this->send_request('AbsoluteTimeout', array('Channel'=>$channel, 'Timeout'=>$timeout));
    }

   /**
    * Change monitoring filename of a channel
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ChangeMonitor
    * @param string $channel the channel to record.
    * @param string $file the new name of the file created in the monitor spool directory.
    */
    function ChangeMonitor($channel, $file)
    {
      return $this->send_request('ChangeMontior', array('Channel'=>$channel, 'File'=>$file));
    }

   /**
    * Execute Command
    *
    * @example examples/sip_show_peer.php Get information about a sip peer
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Command
    * @link http://www.voip-info.org/wiki-Asterisk+CLI
    * @param string $command
    * @param string $actionid message matching variable
    */
    function Command($command, $actionid=NULL)
    {
      $parameters = array('Command'=>$command);
      if($actionid) $parameters['ActionID'] = $actionid;
      return $this->send_request('Command', $parameters);
    }

   /**
    * Enable/Disable sending of events to this manager
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Events
    * @param string $eventmask is either 'on', 'off', or 'system,call,log'
    */
    function Events($eventmask)
    {
      return $this->send_request('Events', array('EventMask'=>$eventmask));
    }

   /**
    * Check Extension Status
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ExtensionState
    * @param string $exten Extension to check state on
    * @param string $context Context for extension
    * @param string $actionid message matching variable
    */
    function ExtensionState($exten, $context, $actionid=NULL)
    {
      $parameters = array('Exten'=>$exten, 'Context'=>$context);
      if($actionid) $parameters['ActionID'] = $actionid;
      return $this->send_request('ExtensionState', $parameters);
    }

   /**
    * Gets a Channel Variable
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+GetVar
    * @link http://www.voip-info.org/wiki-Asterisk+variables
    * @param string $channel Channel to read variable from
    * @param string $variable
    * @param string $actionid message matching variable
    */
    function GetVar($channel, $variable, $actionid=NULL)
    {
      $parameters = array('Channel'=>$channel, 'Variable'=>$variable);
      if($actionid) $parameters['ActionID'] = $actionid;
      return $this->send_request('GetVar', $parameters);
    }

   /**
    * Hangup Channel
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Hangup
    * @param string $channel The channel name to be hungup
    */
    function Hangup($channel)
    {
      return $this->send_request('Hangup', array('Channel'=>$channel));
    }

   /**
    * List IAX Peers
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+IAXpeers
    */
    function IAXPeers()
    {
      return $this->send_request('IAXPeers');
    }

   /**
    * List available manager commands
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ListCommands
    * @param string $actionid message matching variable
    */
    function ListCommands($actionid=NULL)
    {
      if($actionid)
        return $this->send_request('ListCommands', array('ActionID'=>$actionid));
      else
        return $this->send_request('ListCommands');
    }

   /**
    * Logoff Manager
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Logoff
    */
    function Logoff()
    {
      return $this->send_request('Logoff');
    }

   /**
    * Check Mailbox Message Count
    *
    * Returns number of new and old messages.
    *   Message: Mailbox Message Count
    *   Mailbox: <mailboxid>
    *   NewMessages: <count>
    *   OldMessages: <count>
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+MailboxCount
    * @param string $mailbox Full mailbox ID <mailbox>@<vm-context>
    * @param string $actionid message matching variable
    */
    function MailboxCount($mailbox, $actionid=NULL)
    {
      $parameters = array('Mailbox'=>$mailbox);
      if($actionid) $parameters['ActionID'] = $actionid;
      return $this->send_request('MailboxCount', $parameters);
    }

   /**
    * Check Mailbox
    *
    * Returns number of messages.
    *   Message: Mailbox Status
    *   Mailbox: <mailboxid>
    *   Waiting: <count>
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+MailboxStatus
    * @param string $mailbox Full mailbox ID <mailbox>@<vm-context>
    * @param string $actionid message matching variable
    */
    function MailboxStatus($mailbox, $actionid=NULL)
    {	
      $parameters = array('Mailbox'=>$mailbox);
      if($actionid) $parameters['ActionID'] = $actionid;
      return $this->send_request('MailboxStatus', $parameters);
    }

   /**
    * Monitor a channel
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Monitor
    * @param string $channel
    * @param string $file
    * @param string $format
    * @param boolean $mix
    */
    function Monitor($channel, $file=NULL, $format=NULL, $mix=NULL)
    {
      $parameters = array('Channel'=>$channel);
      if($file) $parameters['File'] = $file;
      if($format) $parameters['Format'] = $format;
      if(!is_null($file)) $parameters['Mix'] = ($mix) ? 'true' : 'false';
      return $this->send_request('Monitor', $parameters);
    }

   /**
    * Originate Call
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Originate
    * @param string $channel Channel name to call
    * @param string $exten Extension to use (requires 'Context' and 'Priority')
    * @param string $context Context to use (requires 'Exten' and 'Priority')
    * @param string $priority Priority to use (requires 'Exten' and 'Context')
    * @param string $application Application to use
    * @param string $data Data to use (requires 'Application')
    * @param integer $timeout How long to wait for call to be answered (in ms)
    * @param string $callerid Caller ID to be set on the outgoing channel
    * @param string $variable Channel variable to set (VAR1=value1|VAR2=value2)
    * @param string $account Account code
    * @param boolean $async true fast origination
    * @param string $actionid message matching variable
    */
    function Originate($channel,
                       $exten=NULL, $context=NULL, $priority=NULL,
                       $application=NULL, $data=NULL,
                       $timeout=NULL, $callerid=NULL, $variable=NULL, $account=NULL, $async=NULL, $actionid=NULL)
    {
      $parameters = array('Channel'=>$channel);

      if($exten) $parameters['Exten'] = $exten;
      if($context) $parameters['Context'] = $context;
      if($priority) $parameters['Priority'] = $priority;

      if($application) $parameters['Application'] = $application;
      if($data) $parameters['Data'] = $data;

      if($timeout) $parameters['Timeout'] = $timeout;
      if($callerid) $parameters['CallerID'] = $callerid;
      if($variable) $parameters['Variable'] = $variable;
      if($account) $parameters['Account'] = $account;
      if(!is_null($async)) $parameters['Async'] = ($async) ? 'true' : 'false';
      if($actionid) $parameters['ActionID'] = $actionid;

      return $this->send_request('Originate', $parameters);
    }	

   /**
    * List parked calls
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ParkedCalls
    * @param string $actionid message matching variable
    */
    function ParkedCalls($actionid=NULL)
    {
      if($actionid)
        return $this->send_request('ParkedCalls', array('ActionID'=>$actionid));
      else
        return $this->send_request('ParkedCalls');
    }

   /**
    * Ping
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Ping
    */
    function Ping()
    {
      return $this->send_request('Ping');
    }

   /**
    * Queue Add
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueAdd
    * @param string $queue
    * @param string $interface
    * @param integer $penalty
    */
    function QueueAdd($queue, $interface, $penalty=0)
    {
      $parameters = array('Queue'=>$queue, 'Interface'=>$interface);
      if($penalty) $parameters['Penalty'] = $penalty;
      return $this->send_request('QueueAdd', $parameters);
    }

   /**
    * Queue Remove
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueRemove
    * @param string $queue
    * @param string $interface
    */
    function QueueRemove($queue, $interface)
    {
      return $this->send_request('QueueRemove', array('Queue'=>$queue, 'Interface'=>$interface));
    }

   /**
    * Queues
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Queues
    */
    function Queues()
    {
      return $this->send_request('Queues');
    }

   /**
    * Queue Status
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueStatus
    * @param string $actionid message matching variable
    */
    function QueueStatus($actionid=NULL)
    {
      if($actionid)
        return $this->send_request('QueueStatus', array('ActionID'=>$actionid));
      else
        return $this->send_request('QueueStatus');
    }

   /**
    * Redirect
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Redirect
    * @param string $channel
    * @param string $extrachannel
    * @param string $exten
    * @param string $context
    * @param string $priority
    */
    function Redirect($channel, $extrachannel, $exten, $context, $priority)
    {
      return $this->send_request('Redirect', array('Channel'=>$channel, 'ExtraChannel'=>$extrachannel, 'Exten'=>$exten,
                                                   'Context'=>$context, 'Priority'=>$priority));
    }

   /**
    * Set the CDR UserField
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+SetCDRUserField
    * @param string $userfield
    * @param string $channel
    * @param string $append
    */
    function SetCDRUserField($userfield, $channel, $append=NULL)
    {
      $parameters = array('UserField'=>$userfield, 'Channel'=>$channel);
      if($append) $parameters['Append'] = $append;
      return $this->send_request('SetCDRUserField', $parameters);
    }

   /**
    * Set Channel Variable
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+SetVar
    * @param string $channel Channel to set variable for
    * @param string $variable name
    * @param string $value
    */
    function SetVar($channel, $variable, $value)
    {
      return $this->send_request('SetVar', array('Channel'=>$channel, 'Variable'=>$variable, 'Value'=>$value));
    }

   /**
    * Channel Status
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Status
    * @param string $channel
    * @param string $actionid message matching variable
    */
    function Status($channel, $actionid=NULL)
    {
      $parameters = array('Channel'=>$channel);
      if($actionid) $parameters['ActionID'] = $actionid;
      return $this->send_request('Status', $parameters);
    }

   /**
    * Stop monitoring a channel
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+StopMonitor
    * @param string $channel
    */
    function StopMonitor($channel)
    {
      return $this->send_request('StopMonitor', array('Channel'=>$channel));
    }

   /**
    * Dial over Zap channel while offhook
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDialOffhook
    * @param string $zapchannel
    * @param string $number
    */
    function ZapDialOffhook($zapchannel, $number)
    {
      return $this->send_request('ZapDialOffhook', array('ZapChannel'=>$zapchannel, 'Number'=>$number));
    }

   /**
    * Toggle Zap channel Do Not Disturb status OFF
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDNDoff
    * @param string $zapchannel
    */
    function ZapDNDoff($zapchannel)
    {
      return $this->send_request('ZapDNDoff', array('ZapChannel'=>$zapchannel));
    }

   /**
    * Toggle Zap channel Do Not Disturb status ON
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDNDon
    * @param string $zapchannel
    */
    function ZapDNDon($zapchannel)
    {
      return $this->send_request('ZapDNDon', array('ZapChannel'=>$zapchannel));
    }

   /**
    * Hangup Zap Channel
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapHangup
    * @param string $zapchannel
    */
    function ZapHangup($zapchannel)
    {
      return $this->send_request('ZapHangup', array('ZapChannel'=>$zapchannel));
    }

   /**
    * Transfer Zap Channel
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapTransfer
    * @param string $zapchannel
    */
    function ZapTransfer($zapchannel)
    {
      return $this->send_request('ZapTransfer', array('ZapChannel'=>$zapchannel));
    }

   /**
    * Zap Show Channels
    *
    * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapShowChannels
    * @param string $actionid message matching variable
    */
    function ZapShowChannels($actionid=NULL)
    {
      if($actionid)
        return $this->send_request('ZapShowChannels', array('ActionID'=>$actionid));
      else
        return $this->send_request('ZapShowChannels');
    }

   // *********************************************************************************************************
   // **                       MISC                                                                          **
   // *********************************************************************************************************

   /*
    * Log a message
    *
    * @param string $message
    * @param integer $level from 1 to 4
    */
    function log($message, $level=1)
    {
      if($this->pagi != false)
        $this->pagi->conlog($message, $level);
      else
        error_log(date('r') . ' - ' . $message);
    }

   /**
    * Add event handler
    *
    * Known Events include ( http://www.voip-info.org/wiki-asterisk+manager+events )
    *   Link - Fired when two voice channels are linked together and voice data exchange commences.
    *   Unlink - Fired when a link between two voice channels is discontinued, for example, just before call completion.
    *   Newexten -
    *   Hangup -
    *   Newchannel -
    *   Newstate -
    *   Reload - Fired when the "RELOAD" console command is executed.
    *   Shutdown -
    *   ExtensionStatus -
    *   Rename -
    *   Newcallerid -
    *   Alarm -
    *   AlarmClear -
    *   Agentcallbacklogoff -
    *   Agentcallbacklogin -
    *   Agentlogoff -
    *   MeetmeJoin -
    *   MessageWaiting -
    *   join -
    *   leave -
    *   AgentCalled -
    *   ParkedCall - Fired after ParkedCalls
    *   Cdr -
    *   ParkedCallsComplete -
    *   QueueParams -
    *   QueueMember -
    *   QueueStatusEnd -
    *   Status -
    *   StatusComplete -
    *   ZapShowChannels - Fired after ZapShowChannels
    *   ZapShowChannelsComplete -
    *
    * @param string $event type or * for default handler
    * @param string $callback function
    * @return boolean sucess
    */
    function add_event_handler($event, $callback)
    {
      $event = strtolower($event);
      if(isset($this->event_handlers[$event]))
      {
        $this->log("$event handler is already defined, not over-writing.");
        return false;
      }
      $this->event_handlers[$event] = $callback;
      return true;
    }

   /**
    * Process event
    *
    * @access private
    * @param array $parameters
    * @return mixed result of event handler or false if no handler was found
    */
    function process_event($parameters)
    {
      $ret = false;
      $e = strtolower($parameters['Event']);
      $this->log("Got event.. $e");		

      $handler = '';
      if(isset($this->event_handlers[$e])) $handler = $this->event_handlers[$e];
      elseif(isset($this->event_handlers['*'])) $handler = $this->event_handlers['*'];

      if(function_exists($handler))
      {
        $this->log("Execute handler $handler");
        $ret = $handler($e, $parameters, $this->server, $this->port);
      }
      else
        $this->log("No event handler for event '$e'");
      return $ret;
    }
}
