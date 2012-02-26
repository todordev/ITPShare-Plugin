<?php
/**
 * @package		 ITPrism Plugins
 * @subpackage	 Social
 * @copyright    Copyright (C) 2010 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * ITPShare is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/**
 * ITPShare Plugin
 *
 * @package		ITPrism Plugins
 * @subpackage	Social
 */
class plgContentITPShare extends JPlugin {
    
    private $fbLocale        = "en_US";
    private $currentView     = "";
    private $currentOption   = "";
    
    /**
     * Constructor
     *
     * @param object $subject The object to observe
     * @param array  $config  An optional associative array of configuration settings.
     * Recognized key values include 'name', 'group', 'params', 'language'
     * (this list is not meant to be comprehensive).
     */
    public function __construct(&$subject, $config = array()) {
        parent::__construct($subject, $config);
        
        $app =& JFactory::getApplication();
        /* @var $app JApplication */

        if($app->isAdmin()) {
            return;
        }
      
        if($this->params->get("fbDynamicLocale", 0)) {
            $lang = JFactory::getLanguage();
            $locale = $lang->getTag();
            $this->fbLocale = str_replace("-","_",$locale);
        } else {
            $this->fbLocale = $this->params->get("fbLocale", "en_US");
        }
        
        $this->currentView    = JRequest::getCmd("view");
        $this->currentOption  = JRequest::getCmd("option");
        
    }
    
    /**
     * Add social buttons into the article
     *
	 * @param	string	The context of the content being passed to the plugin.
	 * @param	object	The article object.  Note $article->text is also available
	 * @param	object	The article params
	 * @param	int		The 'page' number
	 */
    public function onContentPrepare($context, &$article, &$params, $page = 0) {

        if (!$article OR !isset($this->params)) { return; };            
        
        $app =& JFactory::getApplication();
        /* @var $app JApplication */

        if($app->isAdmin()) {
            return;
        }
        
        $doc     = JFactory::getDocument();
        /* @var $doc JDocumentHtml */
        $docType = $doc->getType();
        
        // Check document type
        if(strcmp("html", $docType) != 0){
            return;
        }
        
        switch($this->currentOption) {
            case "com_content":
                if($this->isContentRestricted($article)) {
                    return;
                }
                break;    
                
            case "com_k2":
                break;
                
            case "com_virtuemart":
                break;
                
            default:
                break;   
        }
        
        // Generate content
		$content      = $this->getContent($article);
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
        
        return;
    }
    
    /**
     * 
     * Checks allowed articles, exluded categories/articles,... for component COM_CONTENT
     * @param object $article
     */
    private function isContentRestricted($article) {
        
    	/** Check for selected views, which will display the buttons. **/   
        /** If there is a specific set and do not match, return an empty string.**/
        $showInArticles     = $this->params->get('showInArticles');
        
        if(!$showInArticles AND (strcmp("article", $this->currentView) == 0)){
            return true;
        }
        
        // Will be displayed in view "caegories"?
        $showInCategories   = $this->params->get('showInCategories');
        if(!$showInCategories AND (strcmp("category", $this->currentView) == 0)){
            return true;
        }
        
        // Will be displayed in view "featured"?
        $showInFeatured   = $this->params->get('showInFeatured');
        if(!$showInFeatured AND (strcmp("featured", $this->currentView) == 0)){
            return true;
        }
        
        if(
            ($showInCategories AND ($this->currentView == "category") )
        OR 
            ($showInFeatured AND ($this->currentView == "featured") )
            ) {
            $articleData        = $this->getArticle($article);
            $article->id        = JArrayHelper::getValue($articleData,'id');
            $article->catid     = JArrayHelper::getValue($articleData,'catid');
            $article->title     = JArrayHelper::getValue($articleData,'title');
            $article->slug      = JArrayHelper::getValue($articleData, 'slug');
            $article->catslug   = JArrayHelper::getValue($articleData,'catslug');
        }
        
        if(empty($article->id)) {
            return true;            
        }
        
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
        
    }
    
    /**
     * Generate content
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @return  string      Returns html code or empty string.
     */
    private function getContent(&$article){
        
        $doc   = JFactory::getDocument();
        /* @var $doc JDocumentHtml */

        if($this->params->get("loadCss")) {
            $doc->addStyleSheet(JURI::root() . "plugins/content/itpshare/style.css");
        }
        
        $url  = $this->getUrl($article);
        $title= $this->getTitle($article);
        
        $html = '
        <div class="itp-share">';
        
        $html .= $this->getTwitter($this->params, $url, $title);
        $html .= $this->getDigg($this->params, $url, $title);
        $html .= $this->getStumbpleUpon($this->params, $url, $title);
        $html .= $this->getLinkedIn($this->params, $url, $title);
        $html .= $this->getTumblr($this->params, $url, $title);
        $html .= $this->getBuffer($this->params, $url, $title);
        $html .= $this->getReddit($this->params, $url, $title);
        $html .= $this->getPinterest($this->params, $url, $title);
        $html .= $this->getReTweetMeMe($this->params, $url, $title);

        $html .= $this->getFacebookLike($this->params, $url, $title);
        $html .= $this->getGooglePlusOne($this->params, $url, $title);
        
        // Gets extra buttons
        $html   .= $this->getExtraButtons($this->params, $url, $title);
        
        $html .= '
        </div>
        <div style="clear:both;"></div>
        ';
    
        return $html;
    }
    
    private function getUrl(&$article) {
        
        $url = JURI::getInstance();
        $domain= $url->getScheme() ."://" . $url->getHost();
        
        switch($this->currentOption) {
            case "com_content":
                $uri = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catslug), false);
                break;    
                
            case "com_k2":
                $uri = $article->link;
                break;
                
            case "com_virtuemart":
                $uri = $article->link;
                break;
                
            default:
                $uri = "";
                break;   
        }
        
        return $domain.$uri;
        
    }
    
    private function getTitle(&$article) {
        
        switch($this->currentOption) {
            case "com_content":
                $title= htmlentities($article->title, ENT_QUOTES, "UTF-8");
                break;    
                
            case "com_k2":
                $title= htmlentities($article->title, ENT_QUOTES, "UTF-8");
                break;
                
            case "com_virtuemart":
                $title = (!empty($article->custom_title)) ? $article->custom_title : $article->product_name;
                $title= htmlentities($title, ENT_QUOTES, "UTF-8");
                break;
                
            default:
                $title = "";
                break;   
        }
        
        return $title;
        
    }
    
    /**
     * 
     * Load an information about article in the view 'category' and 'featured'
     * @param object $article
     * @todo Implement exceptions for Joomla! platform 12.1
     */
    private function getArticle(&$article) {
        
        $db = JFactory::getDbo();
        
        $query = "
            SELECT 
                `#__content`.`id`,
                `#__content`.`catid`,
                `#__content`.`alias`,
                `#__content`.`title`,
                `#__categories`.`alias` as category_alias
            FROM
                `#__content`
            INNER JOIN
                `#__categories`
            ON
                `#__content`.`catid`=`#__categories`.`id`
            WHERE
                `#__content`.`introtext` = " . $db->quote($article->text); 
        
        $db->setQuery($query);
       
        $result = $db->loadAssoc(); 
        if ($db->getErrorNum() != 0) {
            JError::raiseError(500, "System error!", $db->getErrorMsg());
        }
        /*
        try {
            $result = $db->loadAssoc();
        } catch(JDatabaseException $e) {
        } catch(Exception $e) {
        }
        */
        if(!empty($result)) {
            $result['slug']     = $result['alias'] ? $result['id'].':'.$result['alias'] : $result['id'];
            $result['catslug']  = $result['category_alias'] ? $result['catid'].':'.$result['category_alias'] : $result['catid'];
        }
        
        return $result;
    }
    
    /**
     * Generate a code for the extra buttons
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
            
             $html .= '
             	<div class="itp-share-tw">
                	<a href="https://twitter.com/share" class="twitter-share-button" data-url="' . $url . '" data-text="' . $title . '" data-via="' . $params->get("twitterName") . '" data-lang="' . $params->get("twitterLanguage") . '" data-size="' . $params->get("twitterSize") . '" data-related="' . $params->get("twitterRecommend") . '" data-hashtags="' . $params->get("twitterHashtag") . '" data-count="' . $params->get("twitterCounter") . '">Tweet</a>
                	<script type="text/javascript">!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
            	</div>
            ';
        }
         
        return $html;
    }
    
    private function getGooglePlusOne($params, $url, $title){
        $language = "";
        
        if($params->get("plusLocale")) {
            $language = " {lang: '" . $params->get("plusLocale") . "'};";
        }
        
        $html = "";
        if($params->get("plusButton")) {
            $html .= '<div class="itp-share-gone">';
            
            switch($params->get("plusRenderer")) {
                
                case 1:
                    $html .= $this->genGooglePlus($params, $url, $language);
                    break;
                    
                default:
                    $html .= $this->genGooglePlusHTML5($params, $url, $language);
                    break;
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
     * @param string $language
     */
    private function genGooglePlus($params, $url, $language) {
        
        $annotation = "";
        if($params->get("plusAnnotation")) {
            $annotation = ' annotation="' . $params->get("plusAnnotation") . '"';
        }
        
        $html = '<g:plusone size="' . $params->get("plusType") . '" ' . $annotation . ' href="' . $url . '"></g:plusone>';

        
        // Load the JavaScript asynchroning
		if($params->get("loadGooglePlusJsLib")) {
  
$html .= '<script type="text/javascript">';
if($language) {
   $html .= ' window.___gcfg = '.$language;
}

$html .= '
  (function() {
    var po = document.createElement("script"); po.type = "text/javascript"; po.async = true;
    po.src = "https://apis.google.com/js/plusone.js";
    var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(po, s);
  })();
</script>';
				}
				
        return $html;
    }
    
    /**
     * 
     * Render the Google plus one in HTML5 syntax
     * 
     * @param array $params
     * @param string $url
     * @param string $language
     */
    private function genGooglePlusHTML5($params, $url, $language) {
        
        $annotation = "";
        if($params->get("plusAnnotation")) {
            $annotation = ' data-annotation="' . $params->get("plusAnnotation") . '"';
        }
        
        $html = '<div class="g-plusone" data-size="' . $params->get("plusType") . '" ' . $annotation . ' data-href="' . $url . '"></div>';

        // Load the JavaScript asynchroning
		if($params->get("loadGooglePlusJsLib")) {
      
            $html .= '<script type="text/javascript">';
            if($language) {
               $html .= ' window.___gcfg = '.$language;
            }
            
            $html .= '
              (function() {
                var po = document.createElement("script"); po.type = "text/javascript"; po.async = true;
                po.src = "https://apis.google.com/js/plusone.js";
                var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(po, s);
              })();
            </script>';
		}
    				
        return $html;
    }
    
    
    private function getFacebookLike($params, $url, $title){
        
        $html = "";
        if($params->get("facebookLikeButton")) {
            
            $faces = (!$params->get("facebookLikeFaces")) ? "false" : "true";
            
            $layout = $params->get("facebookLikeType", "button_count");
            if(strcmp("box_count", $layout)==0){
                $height = "80";
            } else {
                $height = "25";
            }
            
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
            <iframe src="http://www.facebook.com/plugins/like.php?';
            
            if($params->get("facebookLikeAppId")) {
                $html .= 'app_id=' . $params->get("facebookLikeAppId"). '&amp;';
            }
            
            $html .= 'href=' . rawurlencode($url) . '&amp;send=' . $params->get("facebookLikeSend",0). '&amp;locale=' . $this->fbLocale . '&amp;layout=' . $layout . '&amp;show_faces=' . $faces . '&amp;width=' . $params->get("facebookLikeWidth","450") . '&amp;action=' . $params->get("facebookLikeAction",'like') . '&amp;colorscheme=' . $params->get("facebookLikeColor",'light') . '&amp;height='.$height.'';
            if($params->get("facebookLikeFont")){
                $html .= "&amp;font=" . $params->get("facebookLikeFont");
            }
            if($params->get("facebookLikeAppId")){
                $html .= "&amp;appId=" . $params->get("facebookLikeAppId");
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
            $html .= '<script type="text/javascript" src="http://connect.facebook.net/' . $this->fbLocale . '/all.js#xfbml=1';
            if($params->get("facebookLikeAppId")){
                $html .= '&amp;appId=' . $params->get("facebookLikeAppId"); 
            }
            $html .= '"></script>';
        }
        
        $html .= '
        <fb:like 
        href="' . $url . '" 
        layout="' . $layout . '" 
        show_faces="' . $faces . '" 
        width="' . $params->get("facebookLikeWidth","450") . '" 
        colorscheme="' . $params->get("facebookLikeColor","light") . '"
        send="' . $params->get("facebookLikeSend",0). '" 
        action="' . $params->get("facebookLikeAction",'like') . '" ';

        if($params->get("facebookLikeFont")){
            $html .= 'font="' . $params->get("facebookLikeFont") . '"';
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
                   
       $html .='
<script type="text/javascript">(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/' . $this->fbLocale . '/all.js#xfbml=1';
               if($params->get("facebookLikeAppId")){
                    $html .= '&amp;appId=' . $params->get("facebookLikeAppId"); 
                }
$html .= '"
  fjs.parentNode.insertBefore(js, fjs);
}(document, "script", "facebook-jssdk"));</script>
                   ';
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
        
        $html .= '></div>';
        
        return $html;
        
    }
    
    private function getDigg($params, $url, $title){
        $title = html_entity_decode($title,ENT_QUOTES, "UTF-8");
        
        $html = "";
        if($params->get("diggButton")) {
            
            $html .= '<div class="itp-share-digg">';
            
            // Load the JS library
            if($params->get("loadDiggJsLib")) {
                $html .= '<script type="text/javascript">
(function() {
var s = document.createElement(\'SCRIPT\'), s1 = document.getElementsByTagName(\'SCRIPT\')[0];
s.type = \'text/javascript\';
s.async = true;
s.src = \'http://widgets.digg.com/buttons.js\';
s1.parentNode.insertBefore(s, s1);
})();
</script>';
            }
            
$html .= '<a 
class="DiggThisButton '.$params->get("diggType","DiggCompact") . '"
href="http://digg.com/submit?url=' . rawurlencode($url) . '&amp;title=' . rawurlencode($title) . '" rev="'.$params->get("diggTopic").'" >
</a>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    private function getPinterest($params, $url, $title){
        
        $title = html_entity_decode($title,ENT_QUOTES, "UTF-8");
        
        $html = "";
        if($params->get("pinterestButton")) {
            
            $html .= '<div class="itp-share-pinterest">';
            
            // Load the JS library
            if($params->get("loadPinterestJsLib")) {
                $html .= '<!-- Include ONCE for ALL buttons in the page -->
<script type="text/javascript">
(function() {
    window.PinIt = window.PinIt || { loaded:false };
    if (window.PinIt.loaded) return;
    window.PinIt.loaded = true;
    function async_load(){
        var s = document.createElement("script");
        s.type = "text/javascript";
        s.async = true;
        if (window.location.protocol == "https:")
            s.src = "https://assets.pinterest.com/js/pinit.js";
        else
            s.src = "http://assets.pinterest.com/js/pinit.js";
        var x = document.getElementsByTagName("script")[0];
        x.parentNode.insertBefore(s, x);
    }
    if (window.attachEvent)
        window.attachEvent("onload", async_load);
    else
        window.addEventListener("load", async_load, false);
})();
</script>
';
            }
            
$html .= '<!-- Customize and include for EACH button in the page -->
<a href="http://pinterest.com/pin/create/button/?url=' . rawurlencode($url) . '&amp;description=' . rawurlencode($title) . '" class="pin-it-button" count-layout="'.$params->get("pinterestType").'">Pin It</a>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    private function getStumbpleUpon($params, $url, $title){
        
        $html = "";
        if($params->get("stumbleButton")) {
            
            $html = '
            <div class="itp-share-su">
            <script type="text/javascript" src="http://www.stumbleupon.com/hostedbadge.php?s=' . $params->get("stumbleType",1). '&amp;r=' . rawurlencode($url) . '"></script>
            </div>
            ';
        }
        
        return $html;
    }
    
    private function getBuffer($params, $url, $title){
        
        $html = "";
        if($params->get("bufferButton")) {
            
            $html = '
            <div class="itp-share-buffer">
            <a href="http://bufferapp.com/add" class="buffer-add-button" data-text="' . $title . '" data-url="'.$url.'" data-count="'.$params->get("bufferType").'" data-via="'.$params->get("bufferTwitterName").'">Buffer</a><script type="text/javascript" src="http://static.bufferapp.com/js/button.js"></script>
            </div>
            ';
        }
        
        return $html;
    }
    
    private function getLinkedIn($params, $url, $title){
        
        $html = "";
        if($params->get("linkedInButton")) {
            
            $html = '
            <div class="itp-share-lin">
            <script type="text/javascript" src="http://platform.linkedin.com/in.js"></script><script type="IN/Share" data-url="' . $url . '" data-counter="' . $params->get("linkedInType",'right'). '"></script>
            </div>
            ';

        }
        
        return $html;
    }
    
    private function getReTweetMeMe($params, $url, $title){
        
        $html = "";
        if($params->get("retweetmeButton")) {
            
            $html = '
            <div class="itp-share-retweetme">
            <script type="text/javascript">
tweetmeme_url = "' . $url . '";
tweetmeme_style = "' . $params->get("retweetmeType") . '";
tweetmeme_source = "' . $params->get("twitterName") . '";
</script>
<script type="text/javascript" src="http://tweetmeme.com/i/scripts/button.js"></script>
            </div>';
        }
        
        return $html;
    }
    
    
    
    private function getReddit($params, $url, $title){
        
        $html = "";
        if($params->get("redditButton")) {
            
            $html .= '<div class="itp-share-reddit">';
            $redditType = $params->get("redditType");
            
            $jsButtons = array(1,2,3);
            
            if(in_array($redditType,$jsButtons) ) {
                $html .='<script type="text/javascript">
  reddit_url = "'. $url . '";
  reddit_title = "'.$title.'";
  reddit_bgcolor = "'.$params->get("redditBgColor").'";
  reddit_bordercolor = "'.$params->get("redditBorderColor").'";
  reddit_newwindow = "'.$params->get("redditNewTab").'";
</script>';
            }
                switch($redditType) {
                    
                    case 1:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/static/button/button1.js"></script>';
                        break;

                    case 2:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/static/button/button2.js"></script>';
                        break;
                    case 3:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/static/button/button3.js"></script>';
                        break;
                    case 4:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/buttonlite.js?i=0"></script>';
                        break;
                    case 5:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/buttonlite.js?i=1"></script>';
                        break;
                    case 6:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/buttonlite.js?i=2"></script>';
                        break;
                    case 7:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/buttonlite.js?i=3"></script>';
                        break;
                    case 8:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/buttonlite.js?i=4"></script>';
                        break;
                    case 9:
                        $html .='<script type="text/javascript" src="http://www.reddit.com/buttonlite.js?i=5"></script>';
                        break;
                    case 10:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit6.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;
                    case 11:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit1.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 12:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit2.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 13:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit3.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 14:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit4.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 15:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit5.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 16:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit8.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 17:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit9.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 18:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit10.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 19:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit11.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 20:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit12.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 21:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit13.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                    case 22:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit14.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;   
                                        
                    default:
                        $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="http://www.reddit.com/static/spreddit7.gif" alt="Submit to reddit" border="0" /> </a>';
                        break;
                }
                
                $html .='</div>';
                
        }
        
        return $html;
    }
    
    private function getTumblr($params, $url, $title){
            
        $html = "";
        if($params->get("tumblrButton")) {
            
            $html .= '<div class="itp-share-tbr">';
            
            if($params->get("loadTumblrJsLib")) {
                $html .= '<script type="text/javascript" src="http://platform.tumblr.com/v1/share.js"></script>';
            }
            
            switch($params->get("tumblrType")) {
                
                case 1:
                    $html .='<a href="http://www.tumblr.com/share" title="Share on Tumblr" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:61px; height:20px; background:url(\'http://platform.tumblr.com/v1/share_2.png\') top left no-repeat transparent;">Share on Tumblr</a>';
                    break;

                case 2:
                    $html .='<a href="http://www.tumblr.com/share" title="Share on Tumblr" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'http://platform.tumblr.com/v1/share_3.png\') top left no-repeat transparent;">Share on Tumblr</a>';
                    break;
                case 3:
                    $html .='<a href="http://www.tumblr.com/share" title="Share on Tumblr" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'http://platform.tumblr.com/v1/share_4.png\') top left no-repeat transparent;">Share on Tumblr</a>';
                    break;
                case 4:
                    $html .='<a href="http://www.tumblr.com/share" title="Share on Tumblr" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'http://platform.tumblr.com/v1/share_1T.png\') top left no-repeat transparent;">Share on Tumblr</a>';
                    break;
                case 5:
                    $html .='<a href="http://www.tumblr.com/share" title="Share on Tumblr" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:61px; height:20px; background:url(\'http://platform.tumblr.com/v1/share_2T.png\') top left no-repeat transparent;">Share on Tumblr</a>';
                    break;
                case 6:
                    $html .='<a href="http://www.tumblr.com/share" title="Share on Tumblr" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'http://platform.tumblr.com/v1/share_3T.png\') top left no-repeat transparent;">Share on Tumblr</a>';
                    break;
                case 7:
                    $html .='<a href="http://www.tumblr.com/share" title="Share on Tumblr" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'http://platform.tumblr.com/v1/share_4T.png\') top left no-repeat transparent;">Share on Tumblr</a>';
                    break;   
                                    
                default:
                    $html .='<a href="http://www.tumblr.com/share" title="Share on Tumblr" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'http://platform.tumblr.com/v1/share_1.png\') top left no-repeat transparent;">Share on Tumblr</a>';
                    break;
            }
            
            $html .='</div>';
        }
        
        return $html;
    }
        
}