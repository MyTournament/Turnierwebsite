<?php

class Collation_Helper{

    private $replacement_chars;

    public function __construct(){
        // --------- INSERT NEW EMOJIS AND SPECIAL CHARACTERS HERE (with the corresponding broken database characters) ---------
        $this->replacement_chars = array(
            array("ðŸ”¥", "🔥"),
            array("ðŸ…±ï¸", "🅱️"),
            array("â¤ï¸", "❤️"),
            array("Ã„", "Ä"),
            array("ðŸ²", "🐲")
        );
        // --------- INSERT NEW EMOJIS HERE (with the corresponding broken database characters) ---------
    }

    public function fix_characters_emojis(string $given_string=null){

        if ($given_string == null){
            return $given_string;
        }

        $utf8_string = iconv('UTF-8', 'ISO-8859-1', $given_string);
        
        // string includes only normal characters, including äöüß
        if ($utf8_string != false){
            return $utf8_string;
            
        // string includes characters outside latin1_general_cs -> ISO-8859-1 (most of the time these special characters are emojis)
        } else {
            $found_special_characters = array();
            foreach($this->replacement_chars as $char_pair){
                if (strstr($given_string, $char_pair[0])){
                    array_push($found_special_characters, $char_pair);
                    $given_string = str_replace($char_pair[0], "{placeholder " . sizeof($found_special_characters) . "}", $given_string);
                }
            }

            $utf8_string = iconv('UTF-8', 'ISO-8859-1', $given_string);
            
            for($index = 1; $index <= sizeof($found_special_characters); $index++){
                $utf8_string = str_replace("{placeholder " . $index . "}", $found_special_characters[$index - 1][1], $utf8_string);
            }

            return $utf8_string;
        }
        
    }

    public function fix_characters(string $given_string=null){
        return iconv('UTF-8', 'ISO-8859-1', $given_string);
    }
}
?>