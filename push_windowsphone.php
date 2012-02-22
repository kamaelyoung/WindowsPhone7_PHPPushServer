<?php 

class WPNotificationType {
    const kWPNType_Unknown                  =   -1;
    
    // TILE NOTIFICATIONS
    const kWPNType_Tile_Immediately         =   1;  // sent immediately
    const kWPNType_Tile_Wait450             =   11;
    const kWPNType_Tile_Wait900             =   21;

    // TOAST NOTIFICATIONS
    const kWPNType_Toast_Immediately        =   2;  // sent immediately
    const kWPNType_Toast_Wait450            =   12;
    const kWPNType_Toast_Wait900            =   22;

    // RAW NOTIFICATIONS
    const kWPNType_Raw_Wait450              =   13;
    const kWPNType_Raw_Immediately          =   3;  // sent immediately
    const kWPNType_Raw_Wait900              =   23;
}


class WPNotifObject {
    private $device_uuid;               // destination device URL (obtained from device's application)
    private $notification_type;         // notification type (choose between WPNotificationType consts)
    private $count_badge;               // count badge value (ONLY FOR TILE NOTIFS)
    private $image_url;                 // tile notification image (ONLY FOR TILE NOTIFS)
    private $message_text;              // message text
    private $message_title;             // message title (ONLY FOR TILE NOTIFS)
    private $raw_push_message;          // if you want to set a raw notification set type to raw and assign a value to this property
    private $message_id;                // id of the notification (if applicable)
    private $extra_dictionary;          // extra informations (not sent, only for internal use). it's a dictionary. use setProperty/getPropertyForKey
    
    function __construct($destination_url) {
        $this->device_uuid = $destination_url;
        $this->notification_type = WPNotificationType::kWPNType_Unknown;
        $this->extra_dictionary = array();
    }
    
    public function setProperty($key,$value)           { $this->extra_dictionary[$key] = $value; }
    public function getPropertyForKey($key)            { return $this->extra_dictionary[$key]; }
     
    public function setMessageID($msgid)               { $this->message_id = $msgid; }
    public function getMessageID()                     { return $this->message_id; }
    
    public function setTitle($title)                   { $this->message_title = $title; }
    public function getTitle()                         { return $this->message_title; }
    
    public function setRawMessage($raw)                { $this->raw_push_message = $raw; }
    public function getRawMessage()                    { return $this->raw_push_message; }
    
    public function setMessage($txt)                   { $this->message_text = $txt; }
    public function getMessage()                       { return $this->message_text; }
    
    public function getNotificationType()              { return $this->notification_type; }
    public function setNotificationType($notif_type)   { $this->notification_type = $notif_type; }
    
    public function getDestinationURL()                { return $this->device_uuid; }
    
    public function setImageURL($image_url)            { $this->image_url = $image_url; }
    public function getImageURL()                      { return $this->image_url; }
    
    public function setCountBadge($badge_value)        { $this->count_badge = $badge_value; }
    public function getBadgeValue()                    { return $this->count_badge; }
    
    
    public function getXMLRepresentation() {
        switch ($this->notification_type) {
            // TOAST NOTIFICATION
            case WPNotificationType::kWPNType_Toast_Immediately:
            case WPNotificationType::kWPNType_Toast_Wait450:
            case WPNotificationType::kWPNType_Toast_Wait900:
                return _xml_toast();
                
            // TILE NOTIFICATION
            case WPNotificationType::kWPNType_Tile_Immediately:
            case WPNotificationType::kWPNType_Tile_Wait450:
            case WPNotificationType::kWPNType_Tile_Wait900:
                return _xml_tile();
            
            // RAW NOTIFICATION
            case WPNotificationType::kWPNType_Raw_Immediately:
            case WPNotificationType::kWPNType_Raw_Wait450:
            case WPNotificationType::kWPNType_Raw_Wait900:
                return $this->raw_push_message;
                
            case WPNotificationType::kWPNType_Unknown:
            default:
                return null;
        }
    }
    
    function _xml_tile() {
        $msg = 	"<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
				"<wp:Notification xmlns:wp=\"WPNotification\">" .
				"<wp:Tile>" .
				"<wp:BackgroundImage>$this->image_url</wp:BackgroundImage>" .
				"<wp:Count>$this->count_badge</wp:Count>" .
				"<wp:Title>$this->message_title</wp:Title>" .
				"</wp:Tile>" .
				"</wp:Notification>";
        return $msg;
    }
    
    function _xml_toast() {
        $msg =	"<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
				"<wp:Notification xmlns:wp=\"WPNotification\">" .
				"<wp:Toast>" .
				"<wp:Text1>$this->message_title</wp:Text1>" .
				"<wp:Text2>$this->message_text</wp:Text2>" .
				"</wp:Toast>" .
				"</wp:Notification>";
        return $msg;
    }
}


class WPNotifSender {
    private $queue_notifs;
    private $failed_notifications;
    private $response_statuses;
    private $timeout;
    
    function __construct() {
        $this->queue_notifs = array();
        $this->failed_notifications = array();
        $this->response_statuses = array();
        $this->timeout = 7; // seconds to timeout request
    }
    
    public function pushNotification($notif) {
        $this->queue_notifs[] = $notif;
    }
    
    public function popNotification() {
        if (count($this->queue_notifs) > 0)
            unset ($this->queue_notifs[(count($this->queue_notifs)-1)]);
    }
    
    public function dispatchNotifications() {
        foreach ($this->queue_notifs as $notifObject) {
            $result_dictionary = _dispatchNotification($notifObject);
            // status available http://msdn.microsoft.com/en-us/library/ff941100(v=vs.92).aspx
            if ($result_dictionary["X-NotificationStatus"] != "Received" || $result_dictionary == null)
                // failed to receive
                $this->failed_notifications[] = $notifObject;
            // add to responses list
            $this->response_statuses[] = $result_dictionary;
        }
    }
    
    public function failedNotificationsIDs() {
        $list_ids = array();
        foreach ($this->failed_notifications as $notif)
            $list_ids[] = $notif->getDestinationURL();
        return $list_ids;
    }
    
    public function succededNotificationsIDs() {
        $list_ids = array();
        foreach ($this->queue_notifs as $notif)
            $list_ids[] = $notif->getDestinationURL();
        return $list_ids;
    }
    
    
    public function failedNotifications() {
        return $this->failed_notifications;
    }
    
    public function succededNotificationsSent() {
        return array_diff($this->queue_notifs, $this->failed_notifications);
    }
    
    public function countNotificationsToSend() {
        return count($this->queue_notifs);
    }
    
    public function countFailedNotifications() {
        return count($this->failed_notifications);
    }
    
    public function getNotificationAtIndex($index) {
        return $this->queue_notifs[$index];
    }
    
    public function getResponseOfNotification($index) {
        return $this->response_statuses[$index];
    }
    
    
    // private methods
    
    function _dispatchNotification($notifObject) {
        $msg = $notifObject->getXMLRepresentation();
        if ($msg == null) // invalid configuration for the object
            return null;
        
        $notificationType = $notifObject->getNotificationType();        
        $sendedheaders=  array( 'Content-Type: text/xml',
                                'Accept: application/*',
				"X-NotificationClass: $notificationType"
                              );
        
        $message_id = $notifObject->getMessageID();
	if($message_id!=NULL)
            $sendedheaders[]="X-MessageID: $message_id";
        
        $target = $notifObject->getDestinationURL();
	if($target!=NULL)
            $sendedheaders[]="X-WindowsPhone-Target:$target";
                
        $req = curl_init();
        curl_setopt($req, CURLOPT_HEADER, true); 
	curl_setopt($req, CURLOPT_HTTPHEADER,$sendedheaders); 
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, $msg);
        curl_setopt($req, CURLOPT_URL, $target);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($req, CURLOPT_CONNECTTIMEOUT, $this->timeout);
	$response = curl_exec($req);
	curl_close($req);
 
        return array(
            'X-SubscriptionStatus'     => $this->_get_header_value($response, 'X-SubscriptionStatus'),
            'X-NotificationStatus'     => $this->_get_header_value($response, 'X-NotificationStatus'),
            'X-DeviceConnectionStatus' => $this->_get_header_value($response, 'X-DeviceConnectionStatus')
            );
    }
    
    private function _get_header_value($content, $header) {
        $match = null;
        return preg_match_all("/$header: (.*)/i", $content, $match) ? $match[1][0] : "";
    }
}

?>