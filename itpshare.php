<?php
/**
 * @package         ITPrism Plugins
 * @subpackage      ITPShare
 * @author          Todor Iliev
 * @copyright       Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license         http://www.gnu.org/licenses/gpl-3.0.en.html GNU/GPLv3
 */

use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

// no direct access
defined('_JEXEC') or die;

/**
 * ITPShare Plugin
 *
 * @package        ITPShare
 * @subpackage     Plugins
 */
class plgContentITPShare extends JPlugin
{
    protected static $loaded = array();

    private $locale = 'en_US';
    private $fbLocale = 'en_US';
    private $gshareLocale = 'en';
    private $currentView = '';
    private $currentTask = '';
    private $currentOption = '';
    private $currentLayout = '';

    private $imgPattern = '/src="([^"]*)"/i';

    /**
     * Put social buttons into the article
     *
     * @param    string    $context The context of the content being passed to the plugin.
     * @param    stdClass    $article The article object.  Note $article->text is also available
     * @param    Registry $params  The article params
     * @param    int       $page    The 'page' number
     *
     * @return void
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if (!$article or !isset($this->params) or empty($article->text)) {
            return;
        }

        $options = array('on_content_prepare', 'on_content_prepare_indicator');
        $option  = $this->params->get('trigger_place');

        // Check for correct trigger
        if (!in_array($option, $options, true)) {
            return;
        }

        // Generate content
        $content = $this->processGenerating($context, $article, $params, $page = 0);

        // If there is no result, return void.
        if ($content === null) {
            return;
        }

        if (strcmp($option, 'on_content_prepare_indicator') === 0) {
            $article->text = str_replace('{itpshare}', $content, $article->text);
        } else {
            $position = $this->params->get('position');
            
            switch ($position) {
                case 1: // Top
                    $article->text = $content . $article->text;
                    break;

                case 2: // Bottom
                    $article->text .= $content;
                    break;

                default: // Both
                    $article->text = $content . $article->text . $content;
                    break;
            }

        }

    }

    /**
     * Add social buttons into the article before content.
     *
     * @param    string    $context The context of the content being passed to the plugin.
     * @param    stdClass    $article The article object.  Note $article->text is also available
     * @param    Registry $params  The article params
     * @param    int       $page    The 'page' number
     *
     * @return string
     */
    public function onContentBeforeDisplay($context, &$article, &$params, $page = 0)
    {
        // Check for correct trigger
        if (strcmp('on_content_before_display', $this->params->get('trigger_place')) !== 0) {
            return '';
        }

        // Generate content
        $content = $this->processGenerating($context, $article, $params, $page = 0);

        // If there is no result, return empty string.
        if ($content === null) {
            return '';
        }

        return $content;
    }

    /**
     * Add social buttons into the article after content.
     *
     * @param    string    $context The context of the content being passed to the plugin.
     * @param    stdClass    $article The article object.  Note $article->text is also available
     * @param    Registry $params  The article params
     * @param    int       $page    The 'page' number
     *
     * @return string
     */
    public function onContentAfterDisplay($context, &$article, &$params, $page = 0)
    {
        // Check for correct trigger
        if (strcmp('on_content_after_display', $this->params->get('trigger_place')) !== 0) {
            return '';
        }

        // Generate content
        $content = $this->processGenerating($context, $article, $params, $page = 0);

        // If there is no result, return empty string.
        if ($content === null) {
            return '';
        }

        return $content;
    }

    /**
     * Execute the process of buttons generating.
     *
     * @param string    $context
     * @param stdClass    $article
     * @param Registry $params
     * @param int       $page
     *
     * @return NULL|string
     */
    private function processGenerating($context, &$article, &$params, $page = 0)
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        if ($app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('html', $docType) !== 0) {
            return null;
        }

        // Get request data
        $this->currentOption = $app->input->getCmd('option');
        $this->currentView   = $app->input->getCmd('view');
        $this->currentTask   = $app->input->getCmd('task');
        $this->currentLayout = $app->input->getCmd('layout');

        if ($this->isRestricted($article, $context, $params)) {
            return null;
        }

        // Get locale code automatically
        if ($this->params->get('dynamicLocale', 0)) {
            $lang         = JFactory::getLanguage();
            $locale       = $lang->getTag();
            $this->locale = str_replace('-', '_', $locale);
        }

        if ($this->params->get('loadCss')) {
            $doc->addStyleSheet(JUri::root() . 'plugins/content/itpshare/style.css');
        }

        // Load language file
        $this->loadLanguage();

        // Generate and return content
        return $this->getContent($article, $context);

    }

    private function isRestricted($article, $context, $params)
    {
        switch ($this->currentOption) {
            case 'com_content':
                $result = $this->isContentRestricted($article, $context);
                break;

            case 'com_k2':
                $result = $this->isK2Restricted($article, $context, $params);
                break;

            case 'com_virtuemart':
                $result = $this->isVirtuemartRestricted($article, $context);
                break;

            case 'com_jevents':
                $result = $this->isJEventsRestricted($article, $context);
                break;

            case 'com_easyblog':
                $result = $this->isEasyBlogRestricted($article, $context);
                break;

            case 'com_vipportfolio':
                $result = $this->isVipPortfolioRestricted($context);
                break;

            case 'com_zoo':
                $result = $this->isZooRestricted($context);
                break;

            case 'com_jshopping':
                $result = $this->isJoomShoppingRestricted($article, $context);
                break;

            case 'com_hikashop':
                $result = $this->isHikaShopRestricted($article, $context);
                break;

            case 'com_vipquotes':
                $result = $this->isVipQuotesRestricted($context);
                break;

            case 'com_userideas':
                $result = $this->isUserIdeasRestricted($context);
                break;

            default:
                $result = true;
                break;
        }

        return $result;

    }

    /**
     * Checks allowed articles, excluded categories/articles,... for component COM_CONTENT
     *
     * @param stdClass $article
     * @param string $context
     *
     * @return bool
     */
    private function isContentRestricted(&$article, $context)
    {
        // Check for correct context
        if (false === strpos($context, 'com_content')) {
            return true;
        }

        /** Check for selected views, which will display the buttons. **/
        /** If there is a specific set and do not match, return an empty string.**/
        $showInArticles = $this->params->get('showInArticles');
        if (!$showInArticles and (strcmp('article', $this->currentView) === 0)) {
            return true;
        }

        // Will be displayed in view 'categories'?
        $showInCategories = $this->params->get('showInCategories');
        if (!$showInCategories and (strcmp('category', $this->currentView) === 0)) {
            return true;
        }

        // Will be displayed in view 'featured'?
        $showInFeatured = $this->params->get('showInFeatured');
        if (!$showInFeatured and (strcmp('featured', $this->currentView) === 0)) {
            return true;
        }

        // Exclude articles
        $excludeArticles = $this->params->get('excludeArticles');
        if ($excludeArticles !== null and $excludeArticles !== '') {
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        ArrayHelper::toInteger($excludeArticles);

        // Excluded categories
        $excludedCats = $this->params->get('excludeCats');
        if ($excludedCats !== null and $excludedCats !== '') {
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        ArrayHelper::toInteger($excludedCats);

        // Included Articles
        $includedArticles = $this->params->get('includeArticles');
        if ($includedArticles !== null and $includedArticles !== '') {
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        ArrayHelper::toInteger($includedArticles);

        if (!in_array((int)$article->id, $includedArticles, true)) {
            // Check excluded articles
            if (in_array((int)$article->id, $excludeArticles, true) or in_array((int)$article->catid, $excludedCats, true)) {
                return true;
            }
        }

        $this->prepareContent($article);

        return false;
    }

    private function prepareContent(&$article)
    {
        if ((strcmp($this->currentView, 'category') === 0) and empty($article->catslug)) {
            $article->catslug = $article->id . ':' . $article->alias;
        }
    }

    /**
     * This method does verification for K2 restrictions
     *
     * @param stdClass $article
     * @param string           $context
     * @param Registry        $params
     *
     * @return bool
     */
    private function isK2Restricted(&$article, $context, $params)
    {
        // Check for correct context
        if (strpos($context, 'com_k2') === false) {
            return true;
        }

        if ($article instanceof TableK2Category) {
            return true;
        }

        $displayInItemlist = $this->params->get('k2DisplayInItemlist', 0);
        if (!$displayInItemlist and (strcmp('itemlist', $this->currentView) === 0)) {
            return true;
        }

        $displayInArticles = $this->params->get('k2DisplayInArticles', 0);
        if (!$displayInArticles and (strcmp('item', $this->currentView) === 0)) {
            return true;
        }

        // Exclude articles
        $excludeArticles = $this->params->get('k2_exclude_articles');
        if ($excludeArticles !== null and $excludeArticles !== '') {
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        ArrayHelper::toInteger($excludeArticles);

        // Exluded categories
        $excludedCats = $this->params->get('k2_exclude_cats');
        if ($excludedCats !== null and $excludedCats !== '') {
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        ArrayHelper::toInteger($excludedCats);

        // Included Articles
        $includedArticles = $this->params->get('k2_include_articles');
        if ($includedArticles !== null and $includedArticles !== '') {
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        ArrayHelper::toInteger($includedArticles);

        if (!in_array((int)$article->id, $includedArticles, true)) {
            // Check excluded articles
            if (in_array((int)$article->id, $excludeArticles, true) or in_array((int)$article->catid, $excludedCats, true)) {
                return true;
            }
        }

        $this->prepareK2Object($article, $params);

        return false;
    }

    /**
     * Prepare some elements of the K2 object.
     *
     * @param stdClass $article
     * @param Registry $params
     */
    private function prepareK2Object(&$article, $params)
    {
        if (empty($article->metadesc)) {
            $introtext         = strip_tags($article->introtext);
            $metaDescLimit     = $params->get('metaDescLimit', 150);
            $article->metadesc = substr($introtext, 0, $metaDescLimit);
        }
    }

    /**
     * It's a method that verify restriction for the component "com_easyblog".
     *
     * @param object $article
     * @param string $context
     *
     * @return bool
     */
    private function isEasyBlogRestricted(&$article, $context)
    {
        $allowedViews = array('categories', 'entry', 'latest', 'tags');
        // Check for correct context
        if (strpos($context, 'easyblog') === false) {
            return true;
        }

        // Only put buttons in allowed views
        if (!in_array($this->currentView, $allowedViews, true)) {
            return true;
        }

        // Verify the option for displaying in view 'categories'
        $displayInCategories = $this->params->get('ebDisplayInCategories', 0);
        if (!$displayInCategories and (strcmp('categories', $this->currentView) === 0)) {
            return true;
        }

        // Verify the option for displaying in view 'latest'
        $displayInLatest = $this->params->get('ebDisplayInLatest', 0);
        if (!$displayInLatest and (strcmp('latest', $this->currentView) === 0)) {
            return true;
        }

        // Verify the option for displaying in view 'entry'
        $displayInEntry = $this->params->get('ebDisplayInEntry', 0);
        if (!$displayInEntry and (strcmp('entry', $this->currentView) === 0)) {
            return true;
        }

        // Verify the option for displaying in view 'tags'
        $displayInTags = $this->params->get('ebDisplayInTags', 0);
        if (!$displayInTags and (strcmp('tags', $this->currentView) === 0)) {
            return true;
        }

        $this->prepareEasyBlogObject($article);

        return false;
    }

    private function prepareEasyBlogObject(&$article)
    {
        $article->image_intro = '';
        $matches              = array();

        preg_match($this->imgPattern, $article->content, $matches);
        if (array_key_exists(1, $matches)) {
            $article->image_intro = ArrayHelper::getValue($matches, 1, '');
        }
    }

    /**
     * Do verifications for JEvent extension.
     *
     * @param jIcalEventRepeat $article
     * @param string           $context
     *
     * @return bool
     */
    private function isJEventsRestricted(&$article, $context)
    {
        // Display buttons only in the description
        if (!is_a($article, 'jIcalEventRepeat')) {
            return true;
        };

        // Check for correct context
        if (strpos($context, 'com_jevents') === false) {
            return true;
        }

        // Display only in task 'icalrepeat.detail'
        if (strcmp('icalrepeat.detail', $this->currentTask) !== 0) {
            return true;
        }

        $displayInEvents = $this->params->get('jeDisplayInEvents', 0);
        if (!$displayInEvents) {
            return true;
        }

        return false;
    }

    /**
     * Do verification for Vip Quotes extension. Is it restricted?
     *
     * @param string $context
     *
     * @return bool
     */
    private function isVipQuotesRestricted($context)
    {
        // Check for correct context
        if (strpos($context, 'com_vipquotes') === false) {
            return true;
        }

        // Display only in view 'quote'
        $allowedViews = array('author', 'quote');
        if (!in_array($this->currentView, $allowedViews, true)) {
            return true;
        }

        $displayOnViewQuote = $this->params->get('vipquotes_display_quote', 0);
        if (!$displayOnViewQuote) {
            return true;
        }

        $displayOnViewAuthor = $this->params->get('vipquotes_display_author', 0);
        if (!$displayOnViewAuthor) {
            return true;
        }

        return false;
    }

    /**
     * Do verification for UserIdeas extension. Is it restricted?
     *
     * @param string $context
     *
     * @return bool
     */
    private function isUserIdeasRestricted($context)
    {
        // Check for correct context
        if (strpos($context, 'com_userideas') === false) {
            return true;
        }

        // Display only in view 'details'
        if (strcmp($this->currentView, 'details') !== 0) {
            return true;
        }

        $displayOnViewDetails = $this->params->get('userideas_display_details', 0);
        if (!$displayOnViewDetails) {
            return true;
        }

        return false;
    }


    /**
     *
     * This method does verification for VirtueMart restrictions
     *
     * @param stdClass $article
     * @param string   $context
     *
     * @return bool
     */
    private function isVirtuemartRestricted(&$article, $context)
    {
        // Check for correct context
        if (strpos($context, 'com_virtuemart') === false) {
            return true;
        }

        // Display content only in the view 'productdetails'
        if (strcmp('productdetails', $this->currentView) !== 0) {
            return true;
        }

        // Only display content in the view 'productdetails'.
        $displayInDetails = $this->params->get('vmDisplayInDetails', 0);
        if (!$displayInDetails) {
            return true;
        }

        // Prepare VirtueMart object
        $this->prepareVirtuemartObject($article);

        return false;
    }

    private function prepareVirtuemartObject(&$article)
    {
        $article->image_intro = '';

        if (!empty($article->id)) {
            $db = JFactory::getDbo();
            /** @var $db JDatabaseDriver */

            $query = $db->getQuery(true);

            $query
                ->select('#__virtuemart_medias.file_url')
                ->from('#__virtuemart_medias')
                ->join('RIGHT', '#__virtuemart_product_medias ON #__virtuemart_product_medias.virtuemart_media_id = #__virtuemart_medias.virtuemart_media_id')
                ->where('#__virtuemart_product_medias.virtuemart_product_id=' . (int)$article->id);

            $db->setQuery($query, 0, 1);
            $fileURL = $db->loadResult();
            if (!empty($fileURL)) {
                $article->image_intro = $fileURL;
            }
        }
    }

    /**
     * It's a method that verify restriction for the component "com_vipportfolio".
     *
     * @param string $context
     *
     * @return bool
     */
    private function isVipPortfolioRestricted($context)
    {
        return (bool)(false === strpos($context, 'com_vipportfolio.details'));
    }

    /**
     * It is a method that verify restriction for the component 'com_zoo'.
     *
     * @param string $context
     *
     * @return bool
     */
    private function isZooRestricted($context)
    {
        // Check for correct context
        if (false === strpos($context, 'com_zoo')) {
            return true;
        }

        // Verify the option for displaying in view 'item'
        $displayInItem = $this->params->get('zoo_display', 0);
        if (!$displayInItem) {
            return true;
        }

        // Check for valid view or task
        // I have check for task because if the user comes from view category, the current view is 'null' and the current task is 'item'
        if ((strcmp('item', $this->currentView) !== 0) and (strcmp('item', $this->currentTask) !== 0)) {
            return true;
        }

        // A little hack used to prevent multiple displaying of buttons, because
        // if there are more than one textareas the buttons will be displayed in everyone.
        static $numbers = 0;
        if ((int)$numbers === 1) {
            return true;
        }
        $numbers = 1;

        return false;
    }

    /**
     * It's a method that verify restriction for the component 'com_joomshopping'.
     *
     * @param stdClass $article
     * @param string $context
     *
     * @return bool
     */
    private function isJoomShoppingRestricted(&$article, $context)
    {
        // Check for correct context
        if (false === strpos($context, 'com_content.article')) {
            return true;
        }

        // Check for enabled functionality for that extension
        $displayInDetails = $this->params->get('joomshopping_display', 0);
        if (!$displayInDetails or !isset($article->product_id)) {
            return true;
        }

        $this->prepareJoomShoppingObject($article);

        return false;
    }

    private function prepareJoomShoppingObject(&$article)
    {
        $article->image_intro = '';

        if (!empty($article->product_id)) {
            $db = JFactory::getDbo();
            /** @var $db JDatabaseDriver */

            $query = $db->getQuery(true);

            $query
                ->select('image_name')
                ->from('#__jshopping_products_images')
                ->where('product_id=' . (int)$article->product_id)
                ->order('ordering');

            $db->setQuery($query, 0, 1);
            $imageName = $db->loadResult();
            if (!empty($imageName)) {
                $config               = JSFactory::getConfig();
                $article->image_intro = (isset($config->image_product_live_path)) ? $config->image_product_live_path .'/'. $imageName : '';
            }
        }
    }

    /**
     * It's a method that verify restriction for the component 'com_hikashop'
     *
     * @param stdClass $article
     * @param string $context
     *
     * @return bool
     */
    private function isHikaShopRestricted(&$article, $context)
    {
        // Check for correct context
        if (false === strpos($context, 'text')) {
            return true;
        }

        // Display content only in the view 'product'
        if (strcmp('product', $this->currentView) !== 0) {
            return true;
        }

        // Check for enabled functionality for that extension
        $displayInDetails = $this->params->get('hikashop_display', 0);
        if (!$displayInDetails) {
            return true;
        }

        $this->prepareHikashopObject($article);

        return false;
    }

    private function prepareHikashopObject(&$article)
    {
        $article->image_intro = '';
        $article->id          = null;

        $url = clone JUri::getInstance();

        // Get the URI
        $itemURI = $url->getPath();
        if ($url->getQuery()) {
            $itemURI .= '?' . $url->getQuery();
        }
        $article->link = $itemURI;

        // Get product id
        $app        = JFactory::getApplication();
        $router     = $app->getRouter();
        $parsed     = $router->parse($url);
        $menuItemId = ArrayHelper::getValue($parsed, 'Itemid');

        $article->id = ArrayHelper::getValue($parsed, 'cid');

        // Get product id from menu item
        if (!$article->id and ($menuItemId !== null and $menuItemId > 0)) {
            $menu     = $app->getMenu();
            $menuItem = $menu->getItem($menuItemId);
//            $menuParams = $menuItem->params;
            $productIds = $menuItem->params->get('product_id');

            if (is_array($productIds) and count($productIds) > 0) {
                $article->id = array_shift($productIds);
            }
        }

        if (!empty($article->id)) {
            $db = JFactory::getDbo();
            /** @var $db JDatabaseDriver */

            $qiery = $db->getQuery(true);

            $qiery
                ->select('#__hikashop_product.product_name, #__hikashop_product.product_page_title, #__hikashop_file.file_path')
                ->from('#__hikashop_product')
                ->join('LEFT', '#__hikashop_file ON #__hikashop_product.product_id = #__hikashop_file.file_ref_id')
                ->where('#__hikashop_product.product_id=' . (int)$article->id);

            $db->setQuery($qiery, 0, 1);
            $result = $db->loadObject();

            if ($result !== null and is_object($result)) {
                // Get title
                $article->title = $result->product_page_title;
                if (!$article->title) {
                    $article->title = $result->product_name;
                }

                // Get image
                $config               = hikashop_config();
                $uploadFolder         = $config->get('uploadfolder');
                $article->image_intro = $uploadFolder . $result->file_path;
            }
        }

    }

    /**
     * Generate content
     *
     * @param   stdClass $article
     * @param   string $context
     *
     * @return  string      Returns html code or empty string.
     */
    private function getContent(&$article, $context)
    {
        $url   = $this->getUrl($article);
        $title = $this->getTitle($article);
        $image = $this->getImage($article);

        // Convert the url to short one
        if ($this->params->get('shortener_service')) {
            $url = $this->getShortUrl($url);
        }

        $html = '
        <div style="clear:both;"></div>
        <div class="itp-share">';

        $html .= $this->getTwitter($this->params, $url, $title);
        $html .= $this->getStumbpleUpon($this->params, $url);
        $html .= $this->getLinkedIn($this->params, $url);
        $html .= $this->getTumblr($this->params);
        $html .= $this->getBuffer($this->params, $url, $title, $image);
        $html .= $this->getPinterest($this->params, $url, $title, $image);
        $html .= $this->getReddit($this->params, $url, $title);

        $html .= $this->getFacebookLike($this->params, $url);
        $html .= $this->getGooglePlusOne($this->params, $url);
        $html .= $this->getGoogleShare($this->params, $url);

        // Get extra buttons
        $html .= $this->getExtraButtons($title, $url, $this->params);

        $html .= '
        </div>
        <div style="clear:both;"></div>
        ';

        return $html;
    }

    /**
     * @param object $article
     *
     * @return string
     */
    private function getUrl(&$article)
    {
        $uri    = '';
        $url    = JUri::getInstance();
        $domain = $url->getScheme() . '://' . $url->getHost();

        switch ($this->currentOption) {
            case 'com_content':
                $uri = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catslug), false);
                break;

            case 'com_k2':
                $uri = $article->link;
                break;

            case 'com_virtuemart':
                $uri = $article->link;
                break;

            case 'com_jevents':
                // Display buttons only in the description
                if (is_a($article, 'jIcalEventRepeat')) {
                    $uri = $this->getCurrentURI($url);
                };
                break;

            case 'com_easyblog':
                $uri = EasyBlogRouter::getRoutedURL('index.php?option=com_easyblog&view=entry&id=' . $article->id, false, false);
                break;

            case 'com_vipportfolio':
                $uri = JRoute::_($article->link, false);
                break;

            case 'com_zoo':
                $uri = $this->getCurrentURI($url);
                break;

            case 'com_jshopping':
                $uri = $this->getCurrentURI($url);
                break;

            case 'com_hikashop':
                $uri = $article->link;
                break;

            case 'com_vipquotes':
                $uri = $article->link;
                break;

            case 'com_userideas':
                $uri = JRoute::_($article->link, false);;
                break;

            default:
                $uri = '';
                break;
        }

        // Filter the URL
        $filter = JFilterInput::getInstance();
        $url    = $filter->clean($domain . $uri);

        return $url;
    }

    /**
     * Generate a URI based on current URL.
     *
     * @param JUri $url
     *
     * @return string
     */
    private function getCurrentURI($url)
    {
        $uri = $url->getPath();
        if ($url->getQuery()) {
            $uri .= '?' . $url->getQuery();
        }

        return $uri;
    }

    private function getTitle(&$article)
    {
        $title = '';

        switch ($this->currentOption) {
            case 'com_content':
                $title = $article->title;
                break;

            case 'com_k2':
                $title = $article->title;
                break;

            case 'com_virtuemart':
                $title = (!empty($article->custom_title)) ? $article->custom_title : $article->product_name;
                break;

            case 'com_jevents':
                // Display buttons only in the description
                if (is_a($article, 'jIcalEventRepeat')) {
                    $title = trim($article->title());
                    if (!$title) {
                        $doc = JFactory::getDocument();
                        /**  @var $doc JDocumentHtml */
                        $title = $doc->getTitle();
                    }
                };
                break;

            case 'com_easyblog':
                $title = $article->title;
                break;

            case 'com_vipportfolio':
                $title = $article->title;
                break;

            case 'com_zoo':
                $doc = JFactory::getDocument();
                /**  @var $doc JDocumentHtml */
                $title = $doc->getTitle();
                break;

            case 'com_jshopping':
                $title = $article->title;
                break;

            case 'com_hikashop':
                $title = $article->title;
                break;

            case 'com_vipquotes':
                $title = $article->title;
                break;

            case 'com_userideas':
                $title = $article->title;
                break;

            default:
                $title = '';
                break;
        }

        return $title;

    }

    private function getImage($article)
    {
        $result = '';

        switch ($this->currentOption) {
            case 'com_content':
                if (!empty($article->images)) {
                    $images = json_decode($article->images);
                    if (!empty($images->image_intro)) {
                        $result = JURI::root() . $images->image_intro;
                    }
                }
                break;

            case 'com_k2':
                if (!empty($article->imageSmall)) {
                    $result = JURI::root() . $article->imageSmall;
                }
                break;

            case 'com_easyblog':
                $result = JURI::root() . $article->image_intro;
                break;

            case 'com_virtuemart':
                if (!empty($article->image_intro)) {
                    $result = JURI::root() . $article->image_intro;
                }
                break;

            case 'com_vipportfolio':
                if (!empty($article->image_intro)) {
                    $result = $article->image_intro;
                }
                break;

            case 'com_jshopping':
                if (!empty($article->image_intro)) {
                    $result = $article->image_intro;
                }
                break;

            case 'com_zoo':
                $result = '';
                break;

            case 'com_hikashop':
                if (!empty($article->image_intro)) {
                    $result = JURI::root() . $article->image_intro;
                }
                break;

            case 'com_vipquotes':
                if (!empty($article->image_intro)) {
                    $result = $article->image_intro;
                }
                break;

            default:
                $result = '';
                break;
        }

        return $result;
    }

    /**
     * A method that make a long url to short url
     *
     * @param string $link
     *
     * @return string
     */
    private function getShortUrl($link)
    {
        JLoader::register('ItpSharePluginShortUrl', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'shorturl.php');
        $options = array(
            'login'   => $this->params->get('shortener_login'),
            'api_key' => $this->params->get('shortener_api_key'),
            'service' => $this->params->get('shortener_service'),
        );

        $shortLink = '';

        try {
            $shortUrl  = new ItpSharePluginShortUrl($link, $options);
            $shortLink = $shortUrl->getUrl();

            // Get original link
            if (!$shortLink) {
                $shortLink = $link;
            }
        } catch (Exception $e) {
            JLog::add($e->getMessage());

            // Get original link
            if (!$shortLink) {
                $shortLink = $link;
            }
        }

        return $shortLink;
    }

    /**
     * Generate a code for the extra buttons.
     * Is also replace indicators {URL} and {TITLE} with that of the article.
     *
     * @param string    $title  Article Title
     * @param string    $url    Article URL
     * @param Registry $params Plugin parameters
     *
     * @return string
     */
    private function getExtraButtons($title, $url, &$params)
    {
        $html = '';
        // Extra buttons
        for ($i = 1; $i < 6; $i++) {
            $btnName     = 'ebuttons' . $i;
            $extraButton = $params->get($btnName, '');
            if ($extraButton !== '') {
                $extraButton = str_replace('{URL}', $url, $extraButton);
                $extraButton = str_replace('{TITLE}', $title, $extraButton);
                $html .= $extraButton;
            }
        }

        return $html;
    }

    /**
     * @param Registry $params
     * @param string    $url
     * @param string    $title
     *
     * @return string
     */
    private function getTwitter($params, $url, $title)
    {
        $html = '';
        if ($params->get('twitterButton')) {
            $title = htmlentities($title, ENT_QUOTES, 'UTF-8');

            // Get locale code
            if (!$params->get('dynamicLocale')) {
                $twitterLocale = $params->get('twitterLanguage', 'en');
            } else {
                $locales             = $this->getButtonsLocales($this->locale);
                $twitterLocale = ArrayHelper::getValue($locales, 'twitter', 'en');
            }

            $html = '
             	<div class="itp-share-tw">
                	<a href="https://twitter.com/share" class="twitter-share-button" data-url="' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8')) . '" data-text="' . $title . '" data-via="' . $params->get('twitterName') . '" data-lang="' . $twitterLocale . '" data-size="' . $params->get('twitterSize') . '" data-related="' . $params->get('twitterRecommend') . '" data-hashtags="' . $params->get('twitterHashtag') . '" data-count="' . $params->get('twitterCounter') . '">Tweet</a>';

            if ($params->get('load_twitter_library', 1)) {
                $html .= "<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>";
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getGooglePlusOne($params, $url)
    {
        $html = '';
        if ($params->get('plusButton')) {
            // Get locale code
            if (!$params->get('dynamicLocale')) {
                $plusLocale = $params->get('plusLocale', 'en');
            } else {
                $locales    = $this->getButtonsLocales($this->locale);
                $plusLocale = ArrayHelper::getValue($locales, 'google', 'en');
            }

            $html .= '<div class="itp-share-gone">';

            $annotation = '';
            if ($params->get('plusAnnotation')) {
                $annotation = ' data-annotation="' . $params->get('plusAnnotation') . '"';
            }

            $html .= '<div class="g-plusone" data-size="' . $params->get('plusType') . '" ' . $annotation . ' data-href="' . $url . '"></div>';

            // Load the JavaScript asynchronous
            if ($params->get('loadGoogleJsLib') and !array_key_exists('google', self::$loaded)) {
                $html .= '
<script src="https://apis.google.com/js/platform.js" async defer>
  {lang: "'.$plusLocale.'"}
</script>';
                self::$loaded['google'] = true;
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getFacebookLike($params, $url)
    {
        $html = '';
        if ($params->get('facebookLikeButton')) {
            // Get locale code
            if (!$params->get('dynamicLocale')) {
                $this->fbLocale = $params->get('fbLocale', 'en_US');
            } else {
                $locales        = $this->getButtonsLocales($this->locale);
                $this->fbLocale = ArrayHelper::getValue($locales, 'facebook', 'en_US');
            }

            // Faces
            $faces = (!$params->get('facebookLikeFaces')) ? 'false' : 'true';

            // Layout Styles
            $layout = $params->get('facebookLikeType', 'button_count');

            // Generate code
            $html = '<div class="itp-share-fbl">';

            if ($params->get('facebookRootDiv', 1)) {
                $html .= '<div id="fb-root"></div>';
            }

            if ($params->get('facebookLoadJsLib', 1)) {
                $appId = '';
                if ($params->get('facebookLikeAppId')) {
                    $appId = '&amp;appId=' . $params->get('facebookLikeAppId');
                }

                $html .= '
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/' . $this->fbLocale . '/sdk.js#xfbml=1&version=v2.6' . $appId . '";
  fjs.parentNode.insertBefore(js, fjs);
}(document, \'script\', \'facebook-jssdk\'));</script>';
            }

            $html .= '
            <div
            class="fb-like"
            data-href="' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8')) . '"
            data-share="' . $params->get('facebookLikeShare', 0) . '"
            data-layout="' . $layout . '"
            data-width="' . $params->get('facebookLikeWidth', '450') . '"
            data-show-faces="' . $faces . '"
            data-colorscheme="' . $params->get('facebookLikeColor', 'light') . '"
            data-action="' . $params->get('facebookLikeAction', 'like') . '"';

            if ($params->get('facebookLikeFont')) {
                $html .= ' data-font="' . $params->get('facebookLikeFont') . '" ';
            }

            if ($params->get('facebookKidDirectedSite')) {
                $html .= ' data-kid-directed-site="true"';
            }

            $html .= '></div>';

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getLinkedIn($params, $url)
    {
        $html = '';
        if ($params->get('linkedInButton')) {
            // Get locale code
            if (!$params->get('dynamicLocale')) {
                $locale  = $params->get('linkedInLocale', 'en_US');
            } else {
                $locale  = $this->locale;
            }

            $html = '<div class="itp-share-lin">';

            if ($params->get('load_linkedin_library', 1)) {
                $html .= '<script src="//platform.linkedin.com/in.js">lang: '.$locale.'</script>';
            }

            $html .= '<script type="IN/Share" data-url="' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8')) . '" data-counter="' . $params->get('linkedInType', 'right') . '"></script>
            </div>
            ';
        }

        return $html;
    }

    /**
     * @param Registry $params
     * @param string    $url
     * @param string    $title
     *
     * @return string
     */
    private function getReddit($params, $url, $title)
    {
        $html = '';
        if ($params->get('redditButton')) {
            $url   = rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8'));
            $title = htmlentities($title, ENT_QUOTES, 'UTF-8');

            $alt   = JText::_('PLG_CONTENT_ITPSHARE_SUBMIT_REDDIT');
            
            $html .= '<div class="itp-share-reddit">';
            $redditType = (int)$params->get('redditType');

            $jsButtons  = range(1, 9);

            if (in_array($redditType, $jsButtons, true)) {
                $html .= '<script>
  reddit_url = "' . $url . '";
  reddit_title = "' . $title . '";
  reddit_bgcolor = "' . $params->get('redditBgColor') . '";
  reddit_bordercolor = "' . $params->get('redditBorderColor') . '";
  reddit_newwindow = "' . $params->get('redditNewTab') . '";
</script>';
            }
            switch ($redditType) {
                case 1:
                    $html .= '<script src="//www.reddit.com/static/button/button1.js"></script>';
                    break;
                case 2:
                    $html .= '<script src="//www.reddit.com/static/button/button2.js"></script>';
                    break;
                case 3:
                    $html .= '<script src="//www.reddit.com/static/button/button3.js"></script>';
                    break;
                case 4:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=0"></script>';
                    break;
                case 5:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=1"></script>';
                    break;
                case 6:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=2"></script>';
                    break;
                case 7:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=3"></script>';
                    break;
                case 8:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=4"></script>';
                    break;
                case 9:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=5"></script>';
                    break;
                case 10:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit6.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 11:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit1.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 12:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit2.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 13:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit3.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 14:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit4.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 15:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit5.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 16:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit8.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 17:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit9.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 18:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit10.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 19:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit11.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 20:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit12.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 21:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit13.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
                case 22:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit14.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;

                default:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit7.gif" alt="' . $alt . '" border="0" /> </a>';
                    break;
            }
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * @param Registry $params
     *
     * @return string
     */
    private function getTumblr($params)
    {
        $html = '';
        if ($params->get('tumblrButton')) {
            $html .= '<div class="itp-share-tbr">';

            if ($params->get('loadTumblrJsLib')) {
                $html .= '<script src="//platform.tumblr.com/v1/share.js"></script>';
            }

            $thumlrTitle = JText::_('PLG_CONTENT_ITPSHARE_SHARE_THUMBLR');
            switch ($params->get('tumblrType')) {
                case 1:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:61px; height:20px; background:url(\'//platform.tumblr.com/v1/share_2.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;

                case 2:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'//platform.tumblr.com/v1/share_3.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 3:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'//platform.tumblr.com/v1/share_4.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 4:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'//platform.tumblr.com/v1/share_1T.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 5:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:61px; height:20px; background:url(\'//platform.tumblr.com/v1/share_2T.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 6:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'//platform.tumblr.com/v1/share_3T.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 7:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'//platform.tumblr.com/v1/share_4T.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;

                default:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'//platform.tumblr.com/v1/share_1.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Registry $params
     * @param string    $url
     * @param string    $title
     * @param string    $image
     *
     * @return string
     */
    private function getPinterest($params, $url, $title, $image)
    {
        $html = '';
        if ($params->get('pinterestButton')) {
            $bubblePosition = $params->get('pinterestType', 'beside');

            $divClass = (strcmp('above', $bubblePosition) === 0) ? 'itp-share-pinterest-above' : 'itp-share-pinterest';

            $html .= '<div class="' . $divClass . '">';

            if (strcmp('one', $this->params->get('pinterestImages', 'one')) === 0) {
                $button = 'buttonPin';
            } else {
                $button = 'buttonBookmark';
            }

            $media = '';
            if (!empty($image)) {
                $media = '&amp;media=' . rawurlencode($image);
            }

            $large = '';
            $largeSize = 20;
            if ((bool)$this->params->get('pinterestLarge')) {
                $large = ' data-pin-tall="true" ';
                $largeSize = 28;
            }

            $url = '?url=' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8'));
            $description = '&amp;description=' . rawurlencode($title);
            $pin = ' data-pin-count="' . $params->get('pinterestType', 'beside') . '" ';

            switch ($this->params->get('pinterestColor', 'gray')) {
                case 'red':
                    $dataColor = ' data-pin-color="red" ';
                    $color = '//assets.pinterest.com/images/pidgets/pinit_fg_en_rect_red_'.$largeSize.'.png';
                    break;
                case 'white':
                    $dataColor = ' data-pin-color="white" ';
                    $color = '//assets.pinterest.com/images/pidgets/pinit_fg_en_rect_white_'.$largeSize.'.png';
                    break;
                default: //gray
                    $dataColor = '';
                    $color = '//assets.pinterest.com/images/pidgets/pinit_fg_en_rect_gray_'.$largeSize.'.png';
                    break;
            }

            $html .= '<a href="//pinterest.com/pin/create/button/' .$url . $media . $description .'" data-pin-do="'.$button.'" '.$pin. $dataColor .$large.'><img src="'.$color.'" /></a>';

            // Load the JS library
            if ($params->get('loadPinterestJsLib') and !array_key_exists('pinterest', self::$loaded)) {
                $html .= '<script async defer src="//assets.pinterest.com/js/pinit.js"></script>';
                self::$loaded['pinterest'] = true;
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getStumbpleUpon($params, $url)
    {
        $html = '';
        if ($params->get('stumbleButton')) {
            $html = "
            <div class=\"itp-share-su\">
            <su:badge layout='" . $params->get('stumbleType', 1) . "' location='" . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8')) . "'></su:badge>
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

    /**
     * @param Registry $params
     * @param string    $url
     * @param string    $title
     * @param string    $image
     *
     * @return string
     */
    private function getBuffer($params, $url, $title, $image = '')
    {
        $html = '';
        if ($params->get('bufferButton')) {
            $title = htmlentities($title, ENT_QUOTES, 'UTF-8');

            $picture = '';
            if (!empty($image)) {
                $picture = 'data-picture="' . $image . '"';
            }

            $html = '
            <div class="itp-share-buffer">
            <a href="http://bufferapp.com/add" class="buffer-add-button" ' . $picture . ' data-text="' . $title . '" data-url="' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8')) . '" data-count="' . $params->get("bufferType") . '" data-via="' . $params->get("bufferTwitterName") . '">Buffer</a><script src="//static.bufferapp.com/js/button.js"></script>
            </div>
            ';
        }

        return $html;
    }

    private function getButtonsLocales($locale)
    {
        // Default locales
        $result = array(
            'twitter'  => 'en',
            'facebook' => 'en_US',
            'google'   => 'en'
        );

        // The locales map
        $locales = array(
            'en_US' => array(
                'twitter'  => 'en',
                'facebook' => 'en_US',
                'google'   => 'en'
            ),
            'en_GB' => array(
                'twitter'  => 'en',
                'facebook' => 'en_GB',
                'google'   => 'en_GB'
            ),
            'th_TH' => array(
                'twitter'  => 'th',
                'facebook' => 'th_TH',
                'google'   => 'th'
            ),
            'ms_MY' => array(
                'twitter'  => 'msa',
                'facebook' => 'ms_MY',
                'google'   => 'ms'
            ),
            'tr_TR' => array(
                'twitter'  => 'tr',
                'facebook' => 'tr_TR',
                'google'   => 'tr'
            ),
            'hi_IN' => array(
                'twitter'  => 'hi',
                'facebook' => 'hi_IN',
                'google'   => 'hi'
            ),
            'tl_PH' => array(
                'twitter'  => 'fil',
                'facebook' => 'tl_PH',
                'google'   => 'fil'
            ),
            'zh_CN' => array(
                'twitter'  => 'zh-cn',
                'facebook' => 'zh_CN',
                'google'   => 'zh'
            ),
            'ko_KR' => array(
                'twitter'  => 'ko',
                'facebook' => 'ko_KR',
                'google'   => 'ko'
            ),
            'it_IT' => array(
                'twitter'  => 'it',
                'facebook' => 'it_IT',
                'google'   => 'it'
            ),
            'da_DK' => array(
                'twitter'  => 'da',
                'facebook' => 'da_DK',
                'google'   => 'da'
            ),
            'fr_FR' => array(
                'twitter'  => 'fr',
                'facebook' => 'fr_FR',
                'google'   => 'fr'
            ),
            'pl_PL' => array(
                'twitter'  => 'pl',
                'facebook' => 'pl_PL',
                'google'   => 'pl'
            ),
            'nl_NL' => array(
                'twitter'  => 'nl',
                'facebook' => 'nl_NL',
                'google'   => 'nl'
            ),
            'id_ID' => array(
                'twitter'  => 'in',
                'facebook' => 'nl_NL',
                'google'   => 'in'
            ),
            'hu_HU' => array(
                'twitter'  => 'hu',
                'facebook' => 'hu_HU',
                'google'   => 'hu'
            ),
            'fi_FI' => array(
                'twitter'  => 'fi',
                'facebook' => 'fi_FI',
                'google'   => 'fi'
            ),
            'es_ES' => array(
                'twitter'  => 'es',
                'facebook' => 'es_ES',
                'google'   => 'es'
            ),
            'ja_JP' => array(
                'twitter'  => 'ja',
                'facebook' => 'ja_JP',
                'google'   => 'ja'
            ),
            'nn_NO' => array(
                'twitter'  => 'no',
                'facebook' => 'nn_NO',
                'google'   => 'no'
            ),
            'ru_RU' => array(
                'twitter'  => 'ru',
                'facebook' => 'ru_RU',
                'google'   => 'ru'
            ),
            'pt_PT' => array(
                'twitter'  => 'pt',
                'facebook' => 'pt_PT',
                'google'   => 'pt'
            ),
            'pt_BR' => array(
                'twitter'  => 'pt',
                'facebook' => 'pt_BR',
                'google'   => 'pt'
            ),
            'sv_SE' => array(
                'twitter'  => 'sv',
                'facebook' => 'sv_SE',
                'google'   => 'sv'
            ),
            'zh_HK' => array(
                'twitter'  => 'zh-tw',
                'facebook' => 'zh_HK',
                'google'   => 'zh_HK'
            ),
            'zh_TW' => array(
                'twitter'  => 'zh-tw',
                'facebook' => 'zh_TW',
                'google'   => 'zh_TW'
            ),
            'de_DE' => array(
                'twitter'  => 'de',
                'facebook' => 'de_DE',
                'google'   => 'de'
            ),
            'bg_BG' => array(
                'twitter'  => 'en',
                'facebook' => 'bg_BG',
                'google'   => 'bg'
            ),

        );

        if (isset($locales[$locale])) {
            $result = $locales[$locale];
        }

        return $result;
    }

    /**
     * @param Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getGoogleShare($params, $url)
    {
        $html = '';
        if ($params->get('gsButton')) {
            // Get locale code
            if (!$params->get('dynamicLocale')) {
                $gshareLocale = $params->get('gsLocale', 'en');
            } else {
                $locales      = $this->getButtonsLocales($this->locale);
                $gshareLocale = ArrayHelper::getValue($locales, 'google', 'en');
            }

            $html .= '<div class="itp-share-gshare">';

            $annotation = '';
            if ($params->get('gsAnnotation')) {
                $annotation = ' data-annotation="' . $params->get('gsAnnotation') . '"';
            }
            
            $size = '';
            if ($params->get('gsType') !== 'vertical-bubble') {
                $size = ' data-height="' .$params->get('gsType') . '"';
            }
            
            $html .= '<div class="g-plus" data-action="share" ' . $annotation . $size . ' data-href="' . $url . '"></div>';

            // Load the JavaScript asynchroning
            if ($params->get('loadGoogleJsLib') and !array_key_exists('google', self::$loaded)) {
                $html .= '<script type="text/javascript">';
                if ($this->gshareLocale) {
                    $html .= ' window.___gcfg = {lang: "' . $gshareLocale . '"}; ';
                }
                $html .= '
  (function() {
    var po = document.createElement(\'script\'); po.type = \'text/javascript\'; po.async = true;
    po.src = \'https://apis.google.com/js/platform.js\';
    var s = document.getElementsByTagName(\'script\')[0]; s.parentNode.insertBefore(po, s);
  })();
                </script>';
            }

            $html .= '</div>';
        }

        return $html;
    }
}
