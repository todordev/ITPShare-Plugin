<?php
/**
 * @package		 ITPShare
 * @subpackage	 Plugins
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2010 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * ITPShare is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/**
 * ITPShare Plugin
 *
 * @package		ITPShare
 * @subpackage	Plugins
 */
class plgContentITPShare extends JPlugin {
    
    private $locale         = "en_US";
    private $fbLocale       = "en_US";
    private $plusLocale     = "en";
    private $gshareLocale   = "en";
    private $twitterLocale  = "en";
    private $currentView    = "";
    private $currentTask    = "";
    private $currentOption  = "";
    private $currentLayout  = "";
    
    private $imgPattern     = '/src="([^"]*)"/i';
    
    /**
     * Add social buttons into the article
     *
	 * @param	string	The context of the content being passed to the plugin.
	 * @param	object	The article object.  Note $article->text is also available
	 * @param	object	The article params
	 * @param	int		The 'page' number
	 */
    public function onContentPrepare($context, &$article, &$params, $page = 0) {

        // Check for correct trigger
        if(strcmp("on_content_prepare", $this->params->get("trigger_place")) != 0) {
            return;
        }
        
        
        // Generate content
        $content      = $this->processGenerating($context, $article, $params, $page = 0);
        $position     = $this->params->get('position');
        
        switch($position){
            case 1:
                $article->text = $content . $article->text;
                break;
            case 2:
                $article->text = $article->text . $content;
                break;
            default:
                $article->text = $content . $article->text . $content;
                break;
        }
        
    }
    
    /**
     * Add social buttons into the article before content.
     *
     * @param	string	The context of the content being passed to the plugin.
     * @param	object	The article object.  Note $article->text is also available
     * @param	object	The article params
     * @param	int		The 'page' number
     */
    public function onContentBeforeDisplay($context, &$article, &$params, $page = 0) {
    
        // Check for correct trigger
        if(strcmp("on_content_before_display", $this->params->get("trigger_place")) != 0) {
            return "";
        }
    
        return $this->processGenerating($context, $article, $params, $page = 0);
    }
    
    /**
     * Add social buttons into the article after content.
     *
     * @param	string	The context of the content being passed to the plugin.
     * @param	object	The article object.  Note $article->text is also available
     * @param	object	The article params
     * @param	int		The 'page' number
     */
    public function onContentAfterDisplay($context, &$article, &$params, $page = 0) {
    
        // Check for correct trigger
        if(strcmp("on_content_after_display", $this->params->get("trigger_place")) != 0) {
            return "";
        }
        
        return $this->processGenerating($context, $article, $params, $page = 0);
    }
    
    /**
     * Execute the process of buttons generating.
     * 
     * @param string    $context
     * @param object    $article
     * @param JRegistry $params
     * @param number    $page
     * @return NULL|string
     */
    private function processGenerating($context, &$article, &$params, $page = 0) {
        
        if (!$article OR !isset($this->params)) { return null; };
        
        $app = JFactory::getApplication();
        /** @var $app JSite **/
        
        if($app->isAdmin()) {
            return null;
        }
        
        $doc     = JFactory::getDocument();
        /**  @var $doc JDocumentHtml **/
        
        // Check document type
        $docType = $doc->getType();
        if(strcmp("html", $docType) != 0){
            return null;
        }
        
        // Get request data
        $this->currentOption  = $app->input->getCmd("option");
        $this->currentView    = $app->input->getCmd("view");
        $this->currentTask    = $app->input->getCmd("task");
        $this->currentLayout  = $app->input->getCmd("layout");
        
        if($this->isRestricted($article, $context, $params)) {
            return null;
        }
        
        // Get locale code automatically
        if($this->params->get("dynamicLocale", 0)) {
            $lang         = JFactory::getLanguage();
            $locale       = $lang->getTag();
            $this->locale = str_replace("-","_",$locale);
        }
        
        if($this->params->get("loadCss")) {
            $doc->addStyleSheet(JURI::root() . "plugins/content/itpshare/style.css");
        }
        
        // Load language file
        $this->loadLanguage();
        
        // Generate and return content
        return $this->getContent($article, $context);
        
    }
    
    private function isRestricted($article, $context, $params) {
    	
    	$result = false;
    	
    	switch($this->currentOption) {
            case "com_content":
            	
            	// It's an implementation of "com_myblog"
            	// I don't know why but $option contains "com_content" for a value
            	// I hope it will be fixed in the future versions of "com_myblog"
            	if(strcmp($context, "com_myblog") != 0) {
                    $result = $this->isContentRestricted($article, $context);
	                break;
            	} 
	                
            case "com_myblog":
                $result = $this->isMyBlogRestricted($article, $context);
                break;
                    
            case "com_k2":
                $result = $this->isK2Restricted($article, $context, $params);
                break;
                
            case "com_virtuemart":
                $result = $this->isVirtuemartRestricted($article, $context);
                break;

            case "com_jevents":
                $result = $this->isJEventsRestricted($article, $context);
                break;

            case "com_vipportfolio":
                $result = $this->isVipPortfolioRestricted($article, $context);
                break;
                
            case "com_zoo":
                $result = $this->isZooRestricted($article, $context);
                break;    
                
             case "com_jshopping":
                $result = $this->isJoomShoppingRestricted($article, $context);
                break;  

            case "com_hikashop":
                $result = $this->isHikaShopRestricted($article, $context);
                break; 

            case "com_vipquotes":
                $result = $this->isVipQuotesRestricted($article, $context);
                break;
                
            default:
                $result = true;
                break;   
        }
        
        return $result;
        
    }
    
    /**
     * 
     * Checks allowed articles, exluded categories/articles,... for component COM_CONTENT
     * @param object $article
     * @param string $context
     */
    private function isContentRestricted(&$article, $context) {
        
        // Check for correct context
        if(strpos($context, "com_content") === false) {
           return true;
        }
        
    	/** Check for selected views, which will display the buttons. **/   
        /** If there is a specific set and do not match, return an empty string.**/
        $showInArticles     = $this->params->get('showInArticles');
        if(!$showInArticles AND (strcmp("article", $this->currentView) == 0)){
            return true;
        }
        
        // Will be displayed in view "categories"?
        $showInCategories   = $this->params->get('showInCategories');
        if(!$showInCategories AND (strcmp("category", $this->currentView) == 0)){
            return true;
        }
        
        // Will be displayed in view "featured"?
        $showInFeatured   = $this->params->get('showInFeatured');
        if(!$showInFeatured AND (strcmp("featured", $this->currentView) == 0)){
            return true;
        }
        
        // Exclude articles
        $excludeArticles = $this->params->get('excludeArticles');
        if(!empty($excludeArticles)){
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        JArrayHelper::toInteger($excludeArticles);
        
        // Exluded categories
        $excludedCats           = $this->params->get('excludeCats');
        if(!empty($excludedCats)){
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        JArrayHelper::toInteger($excludedCats);
        
        // Included Articles
        $includedArticles = $this->params->get('includeArticles');
        if(!empty($includedArticles)){
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        JArrayHelper::toInteger($includedArticles);
        
        if(!in_array($article->id, $includedArticles)) {
            // Check exluded articles
            if(in_array($article->id, $excludeArticles) OR in_array($article->catid, $excludedCats)){
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 
     * This method does verification for K2 restrictions
     * @param jIcalEventRepeat $article
     * @param string $context
     */
    private function isK2Restricted(&$article, $context, $params) {
        
        // Check for correct context
        if(strpos($context, "com_k2") === false) {
           return true;
        }
        
        if($article instanceof TableK2Category){
            return true;
        }
        
        $displayInItemlist     = $this->params->get('k2DisplayInItemlist', 0);
        if(!$displayInItemlist AND (strcmp("itemlist", $this->currentView) == 0)){
            return true;
        }
        
        $displayInArticles     = $this->params->get('k2DisplayInArticles', 0);
        if(!$displayInArticles AND (strcmp("item", $this->currentView) == 0)){
            return true;
        }
        
        // Exclude articles
        $excludeArticles = $this->params->get('k2_exclude_articles');
        if(!empty($excludeArticles)){
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        JArrayHelper::toInteger($excludeArticles);
        
        // Exluded categories
        $excludedCats           = $this->params->get('k2_exclude_cats');
        if(!empty($excludedCats)){
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        JArrayHelper::toInteger($excludedCats);
        
        // Included Articles
        $includedArticles = $this->params->get('k2_include_articles');
        if(!empty($includedArticles)){
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        JArrayHelper::toInteger($includedArticles);
        
        if(!in_array($article->id, $includedArticles)) {
            // Check exluded articles
            if(in_array($article->id, $excludeArticles) OR in_array($article->catid, $excludedCats)){
                return true;
            }
        }
        
        $this->prepareK2Object($article, $params);
        
        return false;
    }
    
    /**
     * Do verifications for JEvent extension
     * 
     * @param jIcalEventRepeat $article
     * @param string $context
     */
    private function isJEventsRestricted(&$article, $context) {
        
        // Display buttons only in the description
        if (!is_a($article, "jIcalEventRepeat")) { 
            return true; 
        };
        
        // Check for correct context
        if(strpos($context, "com_jevents") === false) {
           return true;
        }

        // Display only in task 'icalrepeat.detail'
        if(strcmp("icalrepeat.detail", $this->currentTask) != 0) {
           return true;
        }
        
        $displayInEvents     = $this->params->get('jeDisplayInEvents', 0);
        if(!$displayInEvents){
            return true;
        }
        
        return false;
    }
    
    /**
     * Do verification for Vip Quotes extension. Is it restricted?
     *
     * @param ojbect $article
     * @param string $context
     */
    private function isVipQuotesRestricted(&$article, $context) {
    
        // Check for correct context
        if(strpos($context, "com_vipquotes") === false) {
            return true;
        }
    
        // Display only in view 'quote'
        $allowedViews = array("author", "quote");
        if(!in_array($this->currentView, $allowedViews)) {
            return true;
        }
    
        $displayOnViewQuote     = $this->params->get('vipquotes_display_quote', 0);
        if(!$displayOnViewQuote){
            return true;
        }
        
        $displayOnViewAuthor     = $this->params->get('vipquotes_display_author', 0);
        if(!$displayOnViewAuthor){
            return true;
        }
    
        return false;
    }
    
    /**
     * 
     * This method does verification for VirtueMart restrictions
     * @param stdClass $article
     * @param string $context
     */
    private function isVirtuemartRestricted(&$article, $context) {
            
        // Check for correct context
        if(strpos($context, "com_virtuemart") === false) {
           return true;
        }
        
        // Display content only in the view "productdetails"
        if(strcmp("productdetails", $this->currentView) != 0){
            return true;
        }
        
        // Display content only in the view "productdetails"
        $displayInDetails     = $this->params->get('vmDisplayInDetails', 0);
        if(!$displayInDetails){
            return true;
        }
        
        // Preapare VirtueMart object
        $this->prepareVirtuemartObject($article);
        
        return false;
    }
    
	/**
     * 
     * It's a method that verify restriction for the component "com_myblog"
     * @param object $article
     * @param string $context
     */
	private function isMyBlogRestricted(&$article, $context) {

        // Check for correct context
        if(strpos($context, "myblog") === false) {
           return true;
        }
        
	    // Display content only in the task "view"
        if(strcmp("view", $this->currentTask) != 0){
            return true;
        }
        
        if(!$this->params->get('mbDisplay', 0)){
            return true;
        }
        
        $this->prepareMyBlogObject($article);
        
        return false;
    }
    
	/**
     * 
     * It's a method that verify restriction for the component "com_vipportfolio"
     * @param object $article
     * @param string $context
     */
	private function isVipPortfolioRestricted(&$article, $context) {

        // Check for correct context
        if(strpos($context, "com_vipportfolio") === false) {
           return true;
        }
        
	    // Verify the option for displaying in layout "lineal"
        $displayInLineal     = $this->params->get('vipportfolio_lineal', 0);
        if(!$displayInLineal){
            return true;
        }
        
        return false;
    }
    
	/**
     * 
     * It's a method that verify restriction for the component "com_zoo"
     * @param object $article
     * @param string $context
     */
	private function isZooRestricted(&$article, $context) {
	    
        // Check for correct context
        if(false === strpos($context, "com_zoo")) {
           return true;
        }
        
	    // Verify the option for displaying in view "item"
        $displayInItem     = $this->params->get('zoo_display', 0);
        if(!$displayInItem){
            return true;
        }
        
	    // Check for valid view or task
	    // I have check for task because if the user comes from view category, the current view is "null" and the current task is "item"
        if( (strcmp("item", $this->currentView) != 0 ) AND (strcmp("item", $this->currentTask) != 0 )){
            return true;
        }
        
        // A little hack used to prevent multiple displaying of buttons, becaues
        // if there is more than one textares the buttons will be displayed in everyone.
        static $numbers = 0;
        if($numbers == 1) {
            return true;
        }
        $numbers = 1;
        
        return false;
    }
    
	/**
     * 
     * It's a method that verify restriction for the component "com_joomshopping"
     * @param object $article
     * @param string $context
     */
	private function isJoomShoppingRestricted(&$article, $context) {
        
        // Check for correct context
        if(false === strpos($context, "com_content.article")) {
           return true;
        }
        
	    // Check for enabled functionality for that extension
        $displayInDetails     = $this->params->get('joomshopping_display', 0);
        if(!$displayInDetails OR !isset($article->product_id)){
            return true;
        }
        
        $this->prepareJoomShoppingObject($article);
        
        return false;
    }
    
	/**
     * 
     * It's a method that verify restriction for the component "com_hikashop"
     * @param object $article
     * @param string $context
     */
	private function isHikaShopRestricted(&$article, $context) {
	    
        // Check for correct context
        if(false === strpos($context, "text")) {
           return true;
        }
        
	    // Display content only in the view "product"
        if(strcmp("product", $this->currentView) != 0){
            return true;
        }
        
	    // Check for enabled functionality for that extension
        $displayInDetails     = $this->params->get('hikashop_display', 0);
        if(!$displayInDetails){
            return true;
        }
        
        $this->prepareHikashopObject($article);
        
        return false;
    }
    
	/**
     * 
     * Prepare some elements of the K2 object
     * @param object $article
     * @param JRegistry $params
     */
    private function prepareK2Object(&$article, $params) {
        
        if(empty($article->metadesc)) {
            $introtext         = strip_tags($article->introtext);
            $metaDescLimit     = $params->get("metaDescLimit", 150);
            $article->metadesc = substr($introtext, 0, $metaDescLimit);
        }
            
    }
    
    private function prepareMyBlogObject(&$article) {
        
        $article->image_intro = "";
        $matches = array();
            
        preg_match( $this->imgPattern, $article->fulltext, $matches ) ;
        if(isset($matches[1])) {
            $article->image_intro = JArrayHelper::getValue($matches, 1, "");
        }
            
    }
    
    private function prepareVirtuemartObject(&$article) {
        
        $article->image_intro = "";
        
        if(!empty($article->id)) {
            
            $db = JFactory::getDbo();
            /** @var $db JDatabaseMySQLi **/
            
            $query = $db->getQuery(true);
            
            $query
                ->select("#__virtuemart_medias.file_url")
                ->from("#__virtuemart_medias")
                ->join("RIGHT", "#__virtuemart_product_medias ON #__virtuemart_product_medias.virtuemart_media_id = #__virtuemart_medias.virtuemart_media_id")
                ->where("#__virtuemart_product_medias.virtuemart_product_id=" . (int)$article->id);
              
            $db->setQuery($query, 0, 1);
            $fileURL = $db->loadResult();
            if(!empty($fileURL)) {
                $article->image_intro = $fileURL;
            }
            
        }
    }
    
    private function prepareJoomShoppingObject(&$article) {
        
        $article->image_intro = "";
        
        if(!empty($article->product_id)) {
            
            $db = JFactory::getDbo();
            /** @var $db JDatabaseMySQLi **/
            
            $query = $db->getQuery(true);
            
            $query
                ->select("image_name")
                ->from("#__jshopping_products_images")
                ->where("product_id=" . (int)$article->product_id)
                ->order("ordering");
              
            $db->setQuery($query, 0, 1);
            $imageName = $db->loadResult();
            if(!empty($imageName)) {
                $config = JSFactory::getConfig();
                $article->image_intro = $config->image_product_live_path."/".$imageName;
            }
            
        }
    }
    
    private function prepareHikashopObject(&$article) {
        
        $article->image_intro = "";
        $article->id          = null;
        
        $url = clone JURI::getInstance();
        
        // Get the URI
        $itemURI = $url->getPath();
        if($url->getQuery()) {
            $itemURI .= "?".$url->getQuery();
        }
        $article->link = $itemURI;
        
        // Get product id
        $app         = JFactory::getApplication();
        $router      = $app->getRouter();
        $parsed      = $router->parse($url);
        $menuItemId  = JArrayHelper::getValue($parsed, "Itemid");
        
        $article->id = JArrayHelper::getValue($parsed, "cid");
        
        // Get product id from menu item
        if(!$article->id AND !empty($menuItemId)) {
            $menu           = $app->getMenu();
            $menuItem       = $menu->getItem($menuItemId);
            $menuParams     = $menuItem->params;
            $productIds     = $menuItem->params->get("product_id");
            
            if(!empty($productIds)) {
                $article->id = array_shift($productIds);
            }
            
        }
        
        if(!empty($article->id)) {
            $db = JFactory::getDbo();
            /** @var $db JDatabaseMySQLi **/
            
            $qiery = $db->getQuery(true);
               
            $qiery
                ->select("#__hikashop_product.product_name, #__hikashop_product.product_page_title, #__hikashop_file.file_path")
                ->from("#__hikashop_product")
                ->join("LEFT", "#__hikashop_file ON #__hikashop_product.product_id = #__hikashop_file.file_ref_id")
                ->where("#__hikashop_product.product_id=" . (int)$article->id);
                  
            $db->setQuery($qiery, 0, 1);
            $result = $db->loadObject();
            
            if(!empty($result)) {
                
                // Get title
                $article->title = $result->product_page_title;
                if(!$article->title) {
                    $article->title = $result->product_name;
                }
                
                // Get image
                $config = hikashop_config();
                $uploadFolder = $config->get("uploadfolder");
                $article->image_intro = $uploadFolder.$result->file_path;
            }
        }
        
    }
    
    /**
     * Generate content
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @return  string      Returns html code or empty string.
     */
    private function getContent(&$article, $context){
        
        $url   = $this->getUrl($article, $context);
        $title = $this->getTitle($article, $context);
        $image = $this->getImage($article, $context);
        
    	// Convert the url to short one
        if($this->params->get("shortener_service")) {
            $url = $this->getShortUrl($url);
        }
        
        $html = '
        <div style="clear:both;"></div>
        <div class="itp-share">';
        
        $html .= $this->getTwitter($this->params, $url, $title);
        $html .= $this->getStumbpleUpon($this->params, $url);
        $html .= $this->getLinkedIn($this->params, $url);
        $html .= $this->getTumblr($this->params, $url);
        $html .= $this->getBuffer($this->params, $url, $title, $image);
        $html .= $this->getPinterest($this->params, $url, $title, $image);
        $html .= $this->getReddit($this->params, $url, $title);

        $html .= $this->getFacebookLike($this->params, $url);
        $html .= $this->getGooglePlusOne($this->params, $url);
        $html .= $this->getGoogleShare($this->params, $url);
        
        // Get extra buttons
        $html   .= $this->getExtraButtons($this->params, $url, $title);
        
        $html .= '
        </div>
        <div style="clear:both;"></div>
        ';
    
        return $html;
    }
    
    private function getUrl(&$article, $context) {
        
        $uri     = "";
        $url     = JURI::getInstance();
        $domain  = $url->getScheme() ."://" . $url->getHost();
        
        switch($this->currentOption) {
            case "com_content":
            	
            	// It's an implementation of "com_myblog"
            	// I don't know why but $option contains "com_content" for a value
            	// I hope it will be fixed in the future versions of "com_myblog"
            	if(strcmp($context, "com_myblog") != 0) {
                	$uri = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catslug), false);
                	break;
            	}
            	
            case "com_myblog":
                $uri = $article->permalink;
                break;    
                
                
            case "com_k2":
                $uri = $article->link;
                break;
                
            case "com_virtuemart":
                $uri = $article->link;
                break;
                
            case "com_jevents":
                // Display buttons only in the description
                if (is_a($article, "jIcalEventRepeat")) { 
                    $uri = $this->getCurrentURI($url);
                };
                
                break;

            case "com_vipportfolio":
                $uri = JRoute::_($article->link, false);
                break;
            
            case "com_zoo":
                $uri = $this->getCurrentURI($url);
                break;
                
            case "com_jshopping":
                $uri = $this->getCurrentURI($url);
                break;

            case "com_hikashop":
                $uri = $article->link;
                break;
                
            case "com_vipquotes":
                $uri = $article->link;
                break;
                
            default:
                $uri = "";
                break;   
        }
        
        return $domain.$uri;
        
    }
    
	/**
     * 
     * Generate a URI based on currend URL
     */
    private function getCurrentURI($url) {
        $uri    = $url->getPath();
        if($url->getQuery()) {
            $uri .= "?".$url->getQuery();
        }
        
        return $uri;
    }
    
    private function getTitle(&$article, $context) {
        
        $title = "";
        
        switch($this->currentOption) {
            case "com_content":
            	
            	// It's an implementation of "com_myblog"
            	// I don't know why but $option contains "com_content" for a value
            	// I hope it will be fixed in the future versions of "com_myblog"
            	if(strcmp($context, "com_myblog") != 0) {
            		$title= $article->title;
            		break;
            	}
                
            case "com_myblog":
                $title= $article->title;
                break;    
                
            case "com_k2":
                $title= $article->title;
                break;
                
            case "com_virtuemart":
                $title = (!empty($article->custom_title)) ? $article->custom_title : $article->product_name;
                break;
                
            case "com_jevents":
                // Display buttons only in the description
                if (is_a($article, "jIcalEventRepeat")) { 
                    
                    $title    = JString::trim($article->title());
                    if(!$title) {
                        $doc     = JFactory::getDocument();
                        /**  @var $doc JDocumentHtml **/
                        $title    =  $doc->getTitle();
                    }
                };
                
                break;   

            case "com_vipportfolio":
                $title = $article->title;
                break;
                
            case "com_zoo":
                $doc     = JFactory::getDocument();
                /**  @var $doc JDocumentHtml **/
                $title    =  $doc->getTitle();
                break;

            case "com_jshopping":
                $title = $article->title;
                break;

            case "com_hikashop":
                $title = $article->title;
                break;
                
            case "com_vipquotes":
                $title = $article->title;
                break;
                
            default:
                $title = "";
                break;   
        }
        
        return $title;
        
    }
    
    private function getImage($article, $context) {
        
    	$result = "";
    	
    	switch($this->currentOption) {
            case "com_content":
            	
            	// It's an implementation of "com_myblog"
            	// I don't know why but $option contains "com_content" for a value
            	// I hope it will be fixed in the future versions of "com_myblog"
            	if(strcmp($context, "com_myblog") != 0) {
            	    
            		if(!empty($article->images)) {
            		    $images = json_decode($article->images);
            		    if(!empty($images->image_intro)) {
            		        $result = JURI::root().$images->image_intro;
            		    }
            		}
            		 
	                break;
            	} 
            	
        	// Using contect because the context show us it is com_myblog
    	    // $this->currentOption contains value "com_content" 
        	case "com_myblog":
        	    if(!empty($article->image_intro)) {
        	       $result = str_replace("//", "/", $article->image_intro);
        	    }
        	    break;

            case "com_k2":
    	        if(!empty($article->imageSmall)) {
    		        $result = JURI::root().$article->imageSmall;
        		}
                break;
                
            case "com_virtuemart":
                if(!empty($article->image_intro)) {
                    $result = JURI::root().$article->image_intro;
                }
                break;
            
            case "com_vipportfolio":
    	        if(!empty($article->image_intro)) {
                    $result = JURI::root().$article->image_intro;
                }
                break;
                
            case "com_jshopping":
                if(!empty($article->image_intro)) {
                    $result = $article->image_intro;
                }
                break;
                
            case "com_zoo":
                $result = "";
                break;
                
            case "com_hikashop":
                    
                if(!empty($article->image_intro)) {
                    $result = JURI::root().$article->image_intro;
                }
                
                break;
                
            case "com_vipquotes":
                
                if(!empty($article->image_intro)) {
                    $result = JURI::root().$article->image_intro;
                }
                
                break;
                
            default:
                $result = "";
                break;   
        }
        
        return $result;
        
    }
    
	/**
     * A method that make a long url to short url
     * 
     * @param string $link
     * @param array $params
     * @return string
     */
    private function getShortUrl($link){
        
        JLoader::register("ItpSharePluginShortUrl", dirname(__FILE__).DIRECTORY_SEPARATOR."shorturl.php");
        $options = array(
            "login"     => $this->params->get("shortener_login"),
            "api_key"   => $this->params->get("shortener_api_key"),
            "service"   => $this->params->get("shortener_service"),
        );
        
        try {
        
            $shortUrl  = new ItpSharePluginShortUrl($link,$options);
            $shortLink = $shortUrl->getUrl();
        
            // Get original link
            if(!$shortLink) {
                $shortLink = $link;
            }
        
        } catch(Exception $e) {
        
            JLog::add($e->getMessage());
        
            // Get original link
            if(!$shortLink) {
                $shortLink = $link;
            }
        
        }
        
        return $shortLink;
            
    }
    
    /**
     * Generate a code for the extra buttons. 
     * Is also replace indicators {URL} and {TITLE} with that of the article.
     * 
     * @param string $title Article Title
     * @param string $url   Article URL
     * @param array $params Plugin parameters
     * 
     * @return string
     */
    private function getExtraButtons($params, $url, $title) {
        
        $html  = "";
        // Extra buttons
        for($i=1; $i < 6;$i++) {
            $btnName = "ebuttons" . $i;
            $extraButton = $params->get($btnName, "");
            if(!empty($extraButton)) {
                $extraButton = str_replace("{URL}", $url,$extraButton);
                $extraButton = str_replace("{TITLE}", $title,$extraButton);
                $html  .= $extraButton;
            }
        }
        
        return $html;
    }
    
    private function getTwitter($params, $url, $title){
        
        $html = "";
        if($params->get("twitterButton")) {
            
            $title  = htmlentities($title, ENT_QUOTES, "UTF-8");
            
            // Get locale code
            if(!$params->get("dynamicLocale")) {
                $this->twitterLocale = $params->get("twitterLanguage", "en");
            } else {
                $locales             = $this->getButtonsLocales($this->locale); 
                $this->twitterLocale = JArrayHelper::getValue($locales, "twitter", "en");
            }
            
            $html = '
             	<div class="itp-share-tw">
                	<a href="https://twitter.com/share" class="twitter-share-button" data-url="' . $url . '" data-text="' . $title . '" data-via="' . $params->get("twitterName") . '" data-lang="' . $this->twitterLocale . '" data-size="' . $params->get("twitterSize") . '" data-related="' . $params->get("twitterRecommend") . '" data-hashtags="' . $params->get("twitterHashtag") . '" data-count="' . $params->get("twitterCounter") . '">Tweet</a>';
            
            if($params->get("load_twitter_library", 1)) {
                $html .= '<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';
            }
            
            $html .='</div>';
        }
         
        return $html;
    }
    
    private function getGooglePlusOne($params, $url){
        
        $html = "";
        if($params->get("plusButton")) {
            
            // Get locale code
            if(!$params->get("dynamicLocale")) {
                $this->plusLocale = $params->get("plusLocale", "en");
            } else {
                $locales = $this->getButtonsLocales($this->locale); 
                $this->plusLocale = JArrayHelper::getValue($locales, "google", "en");
            }
            
            $html .= '<div class="itp-share-gone">';
            
            switch($params->get("plusRenderer")) {
                
                case 1:
                    $html .= $this->genGooglePlus($params, $url);
                    break;
                    
                default:
                    $html .= $this->genGooglePlusHTML5($params, $url);
                    break;
            }
            
        // Load the JavaScript asynchroning
		if($params->get("loadGoogleJsLib")) {
  
            $html .= '<script>';
            $html .= ' window.___gcfg = {lang: "' . $this->plusLocale . '"};';
            
            $html .= '
              (function() {
                var po = document.createElement("script"); po.type = "text/javascript"; po.async = true;
                po.src = "https://apis.google.com/js/plusone.js";
                var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(po, s);
              })();
            </script>';
		}
          
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * 
     * Render the Google plus one in standart syntax
     * 
     * @param array $params
     * @param string $url
     */
    private function genGooglePlus($params, $url) {
        
        $annotation = "";
        if($params->get("plusAnnotation")) {
            $annotation = ' annotation="' . $params->get("plusAnnotation") . '"';
        }
        
        $html = '<g:plusone size="' . $params->get("plusType") . '" ' . $annotation . ' href="' . $url . '"></g:plusone>';

        return $html;
    }
    
    /**
     * 
     * Render the Google plus one in HTML5 syntax
     * 
     * @param array $params
     * @param string $url
     */
    private function genGooglePlusHTML5($params, $url) {
        
        $annotation = "";
        if($params->get("plusAnnotation")) {
            $annotation = ' data-annotation="' . $params->get("plusAnnotation") . '"';
        }
        
        $html = '<div class="g-plusone" data-size="' . $params->get("plusType") . '" ' . $annotation . ' data-href="' . $url . '"></div>';
    				
        return $html;
    }
    
    
    private function getFacebookLike($params, $url){
        
        $html = "";
        if($params->get("facebookLikeButton")) {
            
            // Get locale code
            if(!$params->get("dynamicLocale")) {
                $this->fbLocale = $params->get("fbLocale", "en_US");
            } else {
                $locales = $this->getButtonsLocales($this->locale); 
                $this->fbLocale = JArrayHelper::getValue($locales, "facebook", "en_US");
            }
            
            // Faces
            $faces = (!$params->get("facebookLikeFaces")) ? "false" : "true";
            
            // Layout Styles
            $layout = $params->get("facebookLikeType", "button_count");
            if(strcmp("box_count", $layout)==0){
                $height = "80";
            } else {
                $height = "25";
            }
            
            // Generate code
            $html = '<div class="itp-share-fbl">';
            
            switch($params->get("facebookLikeRenderer")) {
                
                case 0: // iframe
                    $html .= $this->genFacebookLikeIframe($params, $url, $layout, $faces, $height);
                break;
                    
                case 1: // XFBML
                    $html .= $this->genFacebookLikeXfbml($params, $url, $layout, $faces, $height);
                break;
             
                default: // HTML5
                   $html .= $this->genFacebookLikeHtml5($params, $url, $layout, $faces, $height);
                break;
            }
            
            $html .="</div>";
        }
        
        return $html;
    }
    
    private function genFacebookLikeIframe($params, $url, $layout, $faces, $height) {
        
        $html = '
            <iframe src="//www.facebook.com/plugins/like.php?';
            
            $html .= 'href=' . rawurlencode($url) . '&amp;send=' . $params->get("facebookLikeSend",0). '&amp;locale=' . $this->fbLocale . '&amp;layout=' . $layout . '&amp;show_faces=' . $faces . '&amp;width=' . $params->get("facebookLikeWidth","450") . '&amp;action=' . $params->get("facebookLikeAction",'like') . '&amp;colorscheme=' . $params->get("facebookLikeColor",'light') . '&amp;height='.$height.'';
            if($params->get("facebookLikeFont")){
                $html .= "&amp;font=" . $params->get("facebookLikeFont");
            }
            if($params->get("facebookLikeAppId")){
                $html .= "&amp;appId=" . $params->get("facebookLikeAppId");
            }
            
            if($params->get("facebookKidDirectedSite")){
                $html .= '&amp;kid_directed_site=true';
            }
            
            $html .= '" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:' . $params->get("facebookLikeWidth", "450") . 'px; height:' . $height . 'px;" allowTransparency="true"></iframe>
        ';
            
        return $html;
    }
    
    private function genFacebookLikeXfbml($params, $url, $layout, $faces, $height) {
        
        $html = "";
                
        if($params->get("facebookRootDiv",1)) {
            $html .= '<div id="fb-root"></div>';
        }
        
    if($params->get("facebookLoadJsLib", 1)) {
           $appId = "";
           if($params->get("facebookLikeAppId")){
               $appId = '&amp;appId=' . $params->get("facebookLikeAppId"); 
           }
            
           $html .= ' 
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/' . $this->fbLocale . '/all.js#xfbml=1'.$appId.'";
  fjs.parentNode.insertBefore(js, fjs);
}(document, \'script\', \'facebook-jssdk\'));</script>';
           
        }
        
        $html .= '
        <fb:like 
        href="' . $url . '" 
        layout="' . $layout . '" 
        show_faces="' . $faces . '" 
        width="' . $params->get("facebookLikeWidth", "450") . '" 
        colorscheme="' . $params->get("facebookLikeColor", "light") . '"
        send="' . $params->get("facebookLikeSend", 0). '" 
        action="' . $params->get("facebookLikeAction", 'like') . '" ';

        if($params->get("facebookLikeFont")){
            $html .= ' font="' . $params->get("facebookLikeFont") . '"';
        }
        
        if($params->get("facebookKidDirectedSite")){
            $html .= ' kid_directed_site="true"';
        }
        
        $html .= '></fb:like>
        ';
        
        return $html;
    }
    
    private function genFacebookLikeHtml5($params, $url, $layout, $faces, $height) {
        
        $html = '';
                
        if($params->get("facebookRootDiv",1)) {
            $html .= '<div id="fb-root"></div>';
        }
                
        if($params->get("facebookLoadJsLib", 1)) {
           $appId = "";
           if($params->get("facebookLikeAppId")){
                $appId = '&amp;appId=' . $params->get("facebookLikeAppId"); 
            }
                
           $html .= ' 
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/' . $this->fbLocale . '/all.js#xfbml=1'.$appId.'";
  fjs.parentNode.insertBefore(js, fjs);
}(document, \'script\', \'facebook-jssdk\'));</script>';
               
        }
        
        $html .= '
            <div 
            class="fb-like" 
            data-href="' . $url . '" 
            data-send="' . $params->get("facebookLikeSend",0). '" 
            data-layout="'.$layout.'" 
            data-width="' . $params->get("facebookLikeWidth","450") . '" 
            data-show-faces="' . $faces . '" 
            data-colorscheme="' . $params->get("facebookLikeColor","light") . '" 
            data-action="' . $params->get("facebookLikeAction",'like') . '"';
                
        if($params->get("facebookLikeFont")){
            $html .= ' data-font="' . $params->get("facebookLikeFont") . '" ';
        }
        
        if($params->get("facebookKidDirectedSite")){
            $html .= ' data-kid-directed-site="true"';
        }
        
        $html .= '></div>';
        
        return $html;
        
    }
    
    private function getLinkedIn($params, $url){
        
        $html = "";
        if($params->get("linkedInButton")) {
            
            $html = '
            <div class="itp-share-lin">';
            
            if($params->get("load_linkedin_library", 1)) {
                $html .= '<script src="//platform.linkedin.com/in.js"></script>';
            }
            
            $html .= '<script type="IN/Share" data-url="' . $url . '" data-counter="' . $params->get("linkedInType", 'right'). '"></script>
            </div>
            ';

        }
        
        return $html;
    }
    
    private function getReddit($params, $url, $title){
        
        $html = "";
        if($params->get("redditButton")) {
            
            $title  = htmlentities($title, ENT_QUOTES, "UTF-8");
            
            $html .= '<div class="itp-share-reddit">';
            $redditType = $params->get("redditType");
            
            $jsButtons = range(1, 9);
            
            if(in_array($redditType, $jsButtons) ) {
                $html .='<script>
  reddit_url = "'. $url . '";
  reddit_title = "'.$title.'";
  reddit_bgcolor = "'.$params->get("redditBgColor").'";
  reddit_bordercolor = "'.$params->get("redditBorderColor").'";
  reddit_newwindow = "'.$params->get("redditNewTab").'";
</script>';
            }
                switch($redditType) {
                    
                    case 1:
                        $html .='<script src="//www.reddit.com/static/button/button1.js"></script>';
                        break;
                    case 2:
                        $html .='<script src="//www.reddit.com/static/button/button2.js"></script>';
                        break;
                    case 3:
                        $html .='<script src="//www.reddit.com/static/button/button3.js"></script>';
                        break;
                    case 4:
                        $html .='<script src="//www.reddit.com/buttonlite.js?i=0"></script>';
                        break;
                    case 5:
                        $html .='<script src="//www.reddit.com/buttonlite.js?i=1"></script>';
                        break;
                    case 6:
                        $html .='<script src="//www.reddit.com/buttonlite.js?i=2"></script>';
                        break;
                    case 7:
                        $html .='<script src="//www.reddit.com/buttonlite.js?i=3"></script>';
                        break;
                    case 8:
                        $html .='<script src="//www.reddit.com/buttonlite.js?i=4"></script>';
                        break;
                    case 9:
                        $html .='<script src="//www.reddit.com/buttonlite.js?i=5"></script>';
                        break;
                    case 10:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit6.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;
                    case 11:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit1.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 12:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit2.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 13:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit3.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 14:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit4.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 15:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit5.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 16:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit8.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 17:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit9.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 18:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit10.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 19:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit11.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 20:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit12.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 21:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit13.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                    case 22:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit14.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;   
                                        
                    default:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit7.gif" alt="'.JText::_("PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT").'" border="0" /> </a>';
                        break;
                }
                
                $html .='</div>';
                
        }
        
        return $html;
    }
    
    private function getTumblr($params, $url){
            
        $html = "";
        if($params->get("tumblrButton")) {
            
            $html .= '<div class="itp-share-tbr">';
            
            if($params->get("loadTumblrJsLib")) {
                $html .= '<script src="//platform.tumblr.com/v1/share.js"></script>';
            }
            
            switch($params->get("tumblrType")) {
                
                case 1:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:61px; height:20px; background:url(\'//platform.tumblr.com/v1/share_2.png\') top left no-repeat transparent;">'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'</a>';
                    break;

                case 2:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'//platform.tumblr.com/v1/share_3.png\') top left no-repeat transparent;">'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'</a>';
                    break;
                case 3:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'//platform.tumblr.com/v1/share_4.png\') top left no-repeat transparent;">'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'</a>';
                    break;
                case 4:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'//platform.tumblr.com/v1/share_1T.png\') top left no-repeat transparent;">'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'</a>';
                    break;
                case 5:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:61px; height:20px; background:url(\'//platform.tumblr.com/v1/share_2T.png\') top left no-repeat transparent;">'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'</a>';
                    break;
                case 6:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'//platform.tumblr.com/v1/share_3T.png\') top left no-repeat transparent;">'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'</a>';
                    break;
                case 7:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'//platform.tumblr.com/v1/share_4T.png\') top left no-repeat transparent;">'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'</a>';
                    break;   
                                    
                default:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'//platform.tumblr.com/v1/share_1.png\') top left no-repeat transparent;">'.JText::_("PLG_CONTENT_ITPSHARE_SHARE_THUMBLR").'</a>';
                    break;
            }
            
            $html .='</div>';
        }
        
        return $html;
    }
    
    private function getPinterest($params, $url, $title, $image){
        
        $html = "";
        if($params->get("pinterestButton")) {
            
            $bubblePosition = $params->get("pinterestType", "beside");
            
            $divClass = (strcmp("above", $bubblePosition) == 0) ? "itp-share-pinterest-above" : "itp-share-pinterest";
            
            $html .= '<div class="'.$divClass.'">';
            
            if(strcmp("one", $this->params->get('pinterestImages', "one")) == 0) {
                
                $media = "";
                if(!empty($image)) {
                    $media = "&amp;media=" . rawurlencode($image);
                }
                
                $html .= '<a href="http://pinterest.com/pin/create/button/?url=' . rawurlencode($url) . $media. '&amp;description=' . rawurlencode($title) . '" data-pin-do="buttonPin" data-pin-config="'.$params->get("pinterestType", "beside").'"><img src="//assets.pinterest.com/images/pidgets/pin_it_button.png" /></a>';
            } else {
                $html .= '<a href="//pinterest.com/pin/create/button/" data-pin-do="buttonBookmark" ><img src="//assets.pinterest.com/images/pidgets/pin_it_button.png" /></a>';
            }
            
            $html .= '</div>';
            
            // Load the JS library
            if($params->get("loadPinterestJsLib")) {
                $html .= '
<script type="text/javascript">
    (function(d){
      var f = d.getElementsByTagName("SCRIPT")[0], p = d.createElement("SCRIPT");
      p.type = "text/javascript";
      p.async = true;
      p.src = "//assets.pinterest.com/js/pinit.js";
      f.parentNode.insertBefore(p, f);
    }(document));
</script>
';
            }
        }
        
        return $html;
    }
    
    private function getStumbpleUpon($params, $url){
        
        $html = "";
        if($params->get("stumbleButton")) {
            
            $html = "
            <div class=\"itp-share-su\">
            <su:badge layout='" . $params->get("stumbleType", 1). "' location='".$url."'></su:badge>
            </div>
            
            <script>
              (function() {
                var li = document.createElement('script'); li.type = 'text/javascript'; li.async = true;
                li.src = ('https:' == document.location.protocol ? 'https:' : 'http:') + '//platform.stumbleupon.com/1/widgets.js';
                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(li, s);
              })();
            </script>
                ";
        }
        
        return $html;
    }
    
    private function getBuffer($params, $url, $title, $image =""){
        
        $html = "";
        if($params->get("bufferButton")) {
            
            $title  = htmlentities($title, ENT_QUOTES, "UTF-8");
            
            $picture = "";
            if(!empty($image)) {
                $picture = 'data-picture="'.$image.'"';
            }
            
            $html = '
            <div class="itp-share-buffer">
            <a href="http://bufferapp.com/add" class="buffer-add-button" '.$picture.' data-text="' . $title . '" data-url="'.$url.'" data-count="'.$params->get("bufferType").'" data-via="'.$params->get("bufferTwitterName").'">Buffer</a><script src="//static.bufferapp.com/js/button.js"></script>
            </div>
            ';
        }
        
        return $html;
    }
        
    private function getButtonsLocales($locale) {
        
         // Default locales
        $result = array(
            "twitter"     => "en",
        	"facebook"    => "en_US",
        	"google"      => "en"
        );
        
        // The locales map
        $locales = array (
            "en_US" => array(
                "twitter"     => "en",
            	"facebook"    => "en_US",
            	"google"      => "en"
            ),
            "en_GB" => array(
                "twitter"     => "en",
            	"facebook"    => "en_GB",
            	"google"      => "en_GB"
            ),
            "th_TH" => array(
                "twitter"     => "th",
            	"facebook"    => "th_TH",
            	"google"      => "th"
            ),
            "ms_MY" => array(
                "twitter"     => "msa",
            	"facebook"    => "ms_MY",
            	"google"      => "ms"
            ),
            "tr_TR" => array(
                "twitter"     => "tr",
            	"facebook"    => "tr_TR",
            	"google"      => "tr"
            ),
            "hi_IN" => array(
                "twitter"     => "hi",
            	"facebook"    => "hi_IN",
            	"google"      => "hi"
            ),
            "tl_PH" => array(
                "twitter"     => "fil",
            	"facebook"    => "tl_PH",
            	"google"      => "fil"
            ),
            "zh_CN" => array(
                "twitter"     => "zh-cn",
            	"facebook"    => "zh_CN",
            	"google"      => "zh"
            ),
            "ko_KR" => array(
                "twitter"     => "ko",
            	"facebook"    => "ko_KR",
            	"google"      => "ko"
            ),
            "it_IT" => array(
                "twitter"     => "it",
            	"facebook"    => "it_IT",
            	"google"      => "it"
            ),
            "da_DK" => array(
                "twitter"     => "da",
            	"facebook"    => "da_DK",
            	"google"      => "da"
            ),
            "fr_FR" => array(
                "twitter"     => "fr",
            	"facebook"    => "fr_FR",
            	"google"      => "fr"
            ),
            "pl_PL" => array(
                "twitter"     => "pl",
            	"facebook"    => "pl_PL",
            	"google"      => "pl"
            ),
            "nl_NL" => array(
                "twitter"     => "nl",
            	"facebook"    => "nl_NL",
            	"google"      => "nl"
            ),
            "id_ID" => array(
                "twitter"     => "in",
            	"facebook"    => "nl_NL",
            	"google"      => "in"
            ),
            "hu_HU" => array(
                "twitter"     => "hu",
            	"facebook"    => "hu_HU",
            	"google"      => "hu"
            ),
            "fi_FI" => array(
                "twitter"     => "fi",
            	"facebook"    => "fi_FI",
            	"google"      => "fi"
            ),
            "es_ES" => array(
                "twitter"     => "es",
            	"facebook"    => "es_ES",
            	"google"      => "es"
            ),
            "ja_JP" => array(
                "twitter"     => "ja",
            	"facebook"    => "ja_JP",
            	"google"      => "ja"
            ),
            "nn_NO" => array(
                "twitter"     => "no",
            	"facebook"    => "nn_NO",
            	"google"      => "no"
            ),
            "ru_RU" => array(
                "twitter"     => "ru",
            	"facebook"    => "ru_RU",
            	"google"      => "ru"
            ),
            "pt_PT" => array(
                "twitter"     => "pt",
            	"facebook"    => "pt_PT",
            	"google"      => "pt"
            ),
            "pt_BR" => array(
                "twitter"     => "pt",
            	"facebook"    => "pt_BR",
            	"google"      => "pt"
            ),
            "sv_SE" => array(
                "twitter"     => "sv",
            	"facebook"    => "sv_SE",
            	"google"      => "sv"
            ),
            "zh_HK" => array(
                "twitter"     => "zh-tw",
            	"facebook"    => "zh_HK",
            	"google"      => "zh_HK"
            ),
            "zh_TW" => array(
                "twitter"     => "zh-tw",
            	"facebook"    => "zh_TW",
            	"google"      => "zh_TW"
            ),
            "de_DE" => array(
                "twitter"     => "de",
            	"facebook"    => "de_DE",
            	"google"      => "de"
            ),
            "bg_BG" => array(
                "twitter"     => "en",
            	"facebook"    => "bg_BG",
            	"google"      => "bg"
            ),
            
        );
        
        if(isset($locales[$locale])) {
            $result = $locales[$locale];
        }
        
        return $result;
        
    }
    
    private function getGoogleShare($params, $url){
        
        $html = "";
        if($params->get("gsButton")) {
            
            // Get locale code
            if(!$params->get("dynamicLocale")) {
                $this->gshareLocale = $params->get("gsLocale", "en");
            } else {
                $locales = $this->getButtonsLocales($this->locale); 
                $this->gshareLocale = JArrayHelper::getValue($locales, "google", "en");
            }
            
            $html .= '<div class="itp-share-gshare">';
            
            switch($params->get("gsRenderer")) {
                
                case 1:
                    $html .= $this->genGoogleShare($params, $url);
                    break;
                    
                default:
                    $html .= $this->genGoogleShareHTML5($params, $url);
                    break;
            }
            
            // Load the JavaScript asynchroning
        	if($params->get("loadGoogleJsLib")) {
        
                $html .= '<script>';
                if($this->gshareLocale) {
                   $html .= ' window.___gcfg = {lang: "'.$this->gshareLocale.'"}; ';
                }
                
                $html .= '
                  (function() {
                    var po = document.createElement("script"); po.type = "text/javascript"; po.async = true;
                    po.src = "https://apis.google.com/js/plusone.js";
                    var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(po, s);
                  })();
                </script>';
            }
          
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * 
     * Render the Google Share in standart syntax
     * 
     * @param array  $params
     * @param string $url
     * @param string $language
     */
    private function genGoogleShare($params, $url) {
        
        $annotation = "";
        if($params->get("gsAnnotation")) {
            $annotation = ' annotation="' . $params->get("gsAnnotation") . '"';
        }
        
        $size = "";
        if($params->get("gsAnnotation") != "vertical-bubble") {
            $size = ' height="' . $params->get("gsType") . '" ';
        }
        
        $html = '<g:plus action="share" ' . $annotation . $size . ' href="' . $url . '"></g:plus>';
        
        return $html;
    }
    
    /**
     * 
     * Render the Google Share in HTML5 syntax
     * 
     * @param array $params
     * @param string $url
     * @param string $language
     */
    private function genGoogleShareHTML5($params, $url) {
        
        $annotation = "";
        if($params->get("gsAnnotation")) {
            $annotation = ' data-annotation="' . $params->get("gsAnnotation") . '"';
        }
        
        $size = "";
        if($params->get("gsAnnotation") != "vertical-bubble") {
            $size = ' data-height="' . $params->get("gsType") . '" ';
        }
        
        $html = '<div class="g-plus" data-action="share" ' . $annotation . $size . ' data-href="' . $url . '"></div>';

        return $html;
    }
    
}