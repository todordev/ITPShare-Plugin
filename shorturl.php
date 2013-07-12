<?php
/**
 * @package      ITPShare
 * @subpackage   Plugins
 * @copyright    Copyright (C) 2010 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * ITPShare is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// no direct access
defined('_JEXEC') or die;

class ItpSharePluginShortUrl {
    
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
        if(isset($options['api_key'])) {
            $this->apiKey = $options['api_key'];
        }
        
        if(isset($options['service'])) {
            $this->service = $options['service'];
        }
        
        if(isset($options['login'])) {
            $this->login = $options['login'];
        }
    }
    
    public function getUrl() {
        
        // Check for installed CURL library
        $installedLibraries = get_loaded_extensions();
        if(!in_array('curl', $installedLibraries)) {
            throw new Exception(JText::_("PLG_CONTENT_ITPSHARE_ERROR_CURL_MISSING"));
        }
        
        switch($this->service) {
            
            case "jmp":
                $this->getBitlyURL("j.mp");
                break;
                
            case "bitlycom":
                $this->getBitlyURL("bitly.com");
                break;
                
            case "tinycc":
                $this->getTinyURL();
                break;

            case "google":
                $this->getGoogleURL();
                break;
                
            default: // bit.ly
                $this->getBitlyURL("bit.ly");
                break;
            
        }
        
        return $this->shortUrl;
    }
    
    /**
     * 
     * Get a short URL from Bitly
     * @param string $domain - bit.ly, j.mp, bitly.com
     */
    protected function getBitlyURL($domain = "bit.ly") {
        
        $url = "http://api.bitly.com/v3/shorten?login=" . $this->login . "&apiKey=" . $this->apiKey. "&longUrl=" . $this->url . "&format=json&domain=".$domain;
        
        $curlObj = curl_init();
        curl_setopt($curlObj, CURLOPT_URL, $url);
        curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);

        //As the API is on https, set the value for CURLOPT_SSL_VERIFYPEER to false. This will stop cURL from verifying the SSL certificate.
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curlObj, CURLOPT_HEADER, 0);
        $response = curl_exec($curlObj);
        
        curl_close($curlObj);
        
        if(!empty($response)) {
            $json = json_decode($response);
            
            if($json->status_code != 200) {
                $errorMessage = "[Bitly service] Message: " . $json->status_txt;
                throw new Exception($errorMessage);
            } else {
                $this->shortUrl = $json->data->url;
            }
        } else {
            throw new Exception(JText::_("PLG_CONTENT_ITPSHARE_ERROR_UNKNOWN_ERROR"));
        }
        
    }
    
	/**
     * 
     * Get a short URL from Tiny.CC
     */
    protected function getTinyURL() {
        
        $url = "http://tiny.cc/?c=rest_api&m=shorten&version=2.0.3&format=json&shortUrl=&longUrl=" . $this->url . "&login=" . $this->login . "&apiKey=" . $this->apiKey;
        
        $curlObj = curl_init();
        curl_setopt($curlObj, CURLOPT_URL, $url);
        curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);

        //As the API is on https, set the value for CURLOPT_SSL_VERIFYPEER to false. This will stop cURL from verifying the SSL certificate.
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curlObj, CURLOPT_HEADER, 0);
        $response = curl_exec($curlObj);
        
        curl_close($curlObj);
        
        if(!empty($response)) {
            $json = json_decode($response);
            
            if(!empty($json->errorCode)) {
                $errorMessage = "[TinyCC service] Message: " . $json->errorMessage;
                throw new Exception($errorMessage);
            } else {
                $this->shortUrl = $json->results->short_url;
            }
        } else {
            throw new Exception(JText::_("PLG_CONTENT_ITPSHARE_ERROR_UNKNOWN_ERROR"));
        }
        
    }
    
    
    /**
     * Get a shor url from Goo.gl
     */
    protected function getGoogleURL() {
        
        $postData = array(
        	'longUrl' => rawurldecode( $this->url ), 
        	'key'     => $this->apiKey
        );
        
        $jsonData = json_encode($postData);
        
        $curlObj = curl_init();
        curl_setopt($curlObj, CURLOPT_URL, 'https://www.googleapis.com/urlshortener/v1/url');
        curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);

        //As the API is on https, set the value for CURLOPT_SSL_VERIFYPEER to false. This will stop cURL from verifying the SSL certificate.
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curlObj, CURLOPT_HEADER, 0);
        curl_setopt($curlObj, CURLOPT_HTTPHEADER, array('Content-type:application/json'));
        curl_setopt($curlObj, CURLOPT_POST, 1);
        curl_setopt($curlObj, CURLOPT_POSTFIELDS, $jsonData);
        $response = curl_exec($curlObj);
        
        curl_close($curlObj);
        
        if(!empty($response)) {
            $json = json_decode($response);
            
            if(!empty($json->error)) {
                $errorMessage = "[Goo.gl service] Message: " . $json->error->message ."; Location: " . $json->error->errors[0]->location;
                throw new Exception($errorMessage);
            } else {
                $this->shortUrl = $json->id;
            }
        } else {
            throw new Exception(JText::_("PLG_CONTENT_ITPSHARE_ERROR_UNKNOWN_ERROR"));
        }
        
    }
}