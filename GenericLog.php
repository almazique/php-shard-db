<?php
namespace Shard;

require_once "LogInterface.php";

class GenericLog implements LogInterface {
    
    const TYPE_AUTO = 1;
    const TYPE_TEXT = 2;
    const TYPE_HTML = 3;
    const TYPE_ERRORLOG = 4;
        
    private $type = 0;
    
    public function __construct($type=self::TYPE_AUTO) {
        if( $type = self::TYPE_AUTO ) {
            $type = (php_sapi_name() == "cli") ? self::TYPE_TEXT : self::TYPE_HTML;
        }
        $this->type = $type;
    }
    
    public function log($string) {
        switch($this->type) {
            case self::TYPE_TEXT: echo $string, "\n"; break;
            case self::TYPE_HTML: echo htmlspecialchars($string), "\n"; break;
            case self::TYPE_ERRORLOG: error_log($string);
            default: break;
        }
    }
    
}