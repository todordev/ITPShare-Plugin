<?php
/**
 * @package      ITPrism Plugins
 * @subpackage   ITPSocialButtons
 * @copyright    Copyright (C) 2010 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * ITPSocialButtons is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die;

class ItpShortUrlSocialButtons extends JObject{
    
    private $url;
    private $shortUrl;
    private $apiKey;
    private $service;
    private $login;
    
    public function __construct($url, $options = array()){
        
        $this->url = $url;
        
        if(!empty($options)) {
            $this->bindOptions($options);
        }
    }
    
    private function bindOptions($options) {
        if(isset($options['apiKey'])) {
            $this->apiKey = $options['apiKey'];
        }
        
        if(isset($options['service'])) {
            $this->service = $options['service'];
        }
        
        if(isset($options['login'])) {
            $this->login = $options['login'];
        }
    }
    
    public function getUrl() {
        
        if(!ini_get('allow_url_fopen')) {
            $this->setError("The PHP option 'allow_url_fopen' is disabled. You must enable the options if you want to use shortener URL services.");
            return null;
        }
        
        switch($this->service) {
            
            case "jmp":
                
                $requestUrl = "http://api.bitly.com/v3/shorten?login=" . $this->login . "&apiKey=" . $this->apiKey. "&longUrl=" . $this->url . "&format=json&domain=j.mp";
                $response   = file_get_contents($requestUrl);
                if(!empty($response)) {
                    $response = json_decode($response, true);
                }
                
                if(isset($response['status_code'])){ 
                    if($response['status_code'] == 200) {
                        $this->shortUrl = $response['data']['url'];
                    } else {
                        $this->setError($response['status_txt']);
                    }
                }else{
                    $this->setError("Unknown error!");
                }
                
                break;
                
            case "tinycc":
                
                $requestUrl = "http://tiny.cc/?c=rest_api&m=shorten&version=2.0.2&format=json&longUrl=" . $this->url . "&login=" . $this->login . "&apiKey=" . $this->apiKey;
                $response   = file_get_contents($requestUrl);
                if(!empty($response)) {
                    $response = json_decode($response, true);
                }
                
                if(!empty($response['errorCode'])){ 
                     $this->setError($response['errorMessage']);
                }else{
                    $this->shortUrl = $response['results']['short_url'];
                }
                
                break;
                
            default: // bit.ly
                
                $requestUrl = "http://api.bitly.com/v3/shorten?login=" . $this->login . "&apiKey=" . $this->apiKey. "&longUrl=" . $this->url . "&format=json&domain=bit.ly";
                $response   = file_get_contents($requestUrl);
                if(!empty($response)) {
                    $response = json_decode($response, true);
                }
                
                if(isset($response['status_code'])){ 
                    if($response['status_code'] == 200) {
                        $this->shortUrl = $response['data']['url'];
                    } else {
                        $this->setError($response['status_txt']);
                    }
                }else{
                    $this->setError("Unknown error!");
                }
                
                break;
            
        }
        
        return $this->shortUrl;
        
    }
}

