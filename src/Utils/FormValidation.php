<?php

namespace Servdebt\SlimCore\Utils;

class FormValidation
{
    static public function sanitizeArray($array): array
    {
        $result=array();
        if(is_array($array)){
            foreach($array as $key=>$value){
                if(is_array($value)){
                    $result[$key] = FormValidation::sanitizeArray($value);
                }
                else if(is_string($value)){
                    $result[$key] = strip_tags(htmlentities(stripslashes($value)));
                }
                else{
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }

    static public function sanitizeString($sting): string|null
    {
        if(is_string($sting)) {
            return strip_tags(htmlentities(stripslashes($sting)));
        }

        return null;
    }
}