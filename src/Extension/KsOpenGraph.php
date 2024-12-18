<?php

/**
 * @version    2.1.1
 * @package    ksopengraph (plugin)
 * @author     Sergey Kuznetsov - mediafoks@google.com
 * @copyright  Copyright (c) 2024 Sergey Kuznetsov
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 */

namespace Joomla\Plugin\Content\KsOpenGraph\Extension;

//kill direct access
\defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Event\Content\ContentPrepareEvent;

final class KsOpenGraph extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected $allowLegacyListeners = false;

    public $pluginNr = 0;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    function realCleanImageUrl($img)
    {

        $imgClean = HTMLHelper::cleanImageURL($img);
        if ($imgClean->url != '') $img =  $imgClean->url;
        return $img;
    }

    public function setImage($image)
    {
        $linkImg = $image;

        $absU = 0;
        // Test if this link is absolute http:// then do not change it
        $pos1 = strpos($image, 'http://');
        if ($pos1 === false) {
        } else {
            $absU = 1;
        }

        // Test if this link is absolute https:// then do not change it
        $pos2 = strpos($image, 'https://');
        if ($pos2 === false) {
        } else {
            $absU = 1;
        }

        if ($absU == 1) {
            $linkImg = $image;
        } else {
            $linkImg = Uri::base(false) . $image;

            if ($image[0] == '/') {
                $myURI = new Uri(Uri::base(false));
                $myURI->setPath($image);
                $linkImg = $myURI->toString();
            } else {
                $linkImg = Uri::base(false) . $image;
            }
        }

        return $linkImg;
    }

    public function catStr($str, $length = 300)
    {
        $str_clean = trim(strip_tags($str)); // удаляем HTML символы и пробелы в начале и конце строки

        if (mb_strlen($str_clean, 'utf-8') <= $length) {
            return $str_clean;
        } else {
            $str_cat = mb_substr($str_clean, 0, $length, 'utf-8'); // обрезаем строку до нужной длины
            $space_pos = mb_strrpos($str_cat, ' ', 0, 'utf-8'); // находим позицию последнего пробела
            $str_cat_space = mb_substr($str_cat, 0, $space_pos, 'utf-8'); // обрезаем строку до пробела
            $new_str = $str_cat_space . '...'; // добавляем троеточие в конец строки

            return $new_str;
        }
    }

    public function renderTag($name, $value, $attr = 'name')
    {
        $app = $this->getApplication();
        $document = $app->getDocument();
        $value = strip_tags(html_entity_decode($value));

        $document->setMetadata(htmlspecialchars($name, ENT_COMPAT, 'UTF-8'), htmlspecialchars($value, ENT_COMPAT, 'UTF-8'), $attr);
    }

    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $app = $this->getApplication();
        $config = Factory::getConfig();
        $document = $app->getDocument();
        $view = $app->input->get('view'); // article, category, featured

        if (!$app->isClient('site') || (int)$this->pluginNr > 0) return; // если это не фронтэнд, то прекращаем работу

        $thisSiteName = $config->get('sitename');
        $thisTitle = '';
        $thisDescription = '';
        $thisImage = '';
        $thisOgType = Uri::current() != Uri::base() ? 'article' : 'website';
        $thisImageDefault = $this->params->get('image_default');
        $twitterEnable = (int) $this->params->get('twitter_enable');


        if ($view == 'featured' && $this->pluginNr == 0) {
            $thisTitle = $document->title;
            $menu_metasesc = $app->getParams()->get('menu-meta_description');
            $thisDescription = isset($menu_metasesc) && $menu_metasesc != '' ? $menu_metasesc : $document->description;
            $thisImage = $thisImageDefault;
            $this->pluginNr = 1;
        } elseif (($view == 'category' || $view == 'categories') && $this->pluginNr == 0) {

            $model_category = $app->bootComponent('com_content')->getMVCFactory()->createModel('Category', 'Site', ['ignore_request' => false]);
            $category = $model_category->getCategory();

            $thisTitle = $category->title != '' ? $category->title : $document->title;

            if ($app->input->get('option') == 'com_contact') {
                $model_contact_category = $app->bootComponent('com_contact')->getMVCFactory()->createModel('Category', 'Site', ['ignore_request' => false]);
                $contactCategory = $model_contact_category->getCategory();
                $thisDescription = isset($contactCategory->metadesc) && $contactCategory->metadesc != '' ? $contactCategory->metadesc : $document->description;
            } else {
                $thisDescription = isset($category->metadesc) && $category->metadesc != '' ? $category->metadesc : $document->description;
            }

            $image = json_decode($category->params)->image;
            $thisImage = $image != '' ? $image : $thisImageDefault;
            $this->pluginNr = 1;
        } elseif ($view == 'tag' && $this->pluginNr == 0) {
            $model_tag = $app->bootComponent('com_tags')->getMVCFactory()->createModel('Tag', 'Site', ['ignore_request' => false]);
            $tag = $model_tag->getItem()[0];
            $tag_title = $tag->title;
            $thisTitle = $tag_title != '' ? $tag_title : $document->title;

            $thisDescription = isset($tag->metadesc) && $tag->metadesc != '' ? $tag->metadesc : $document->description;
            $images = json_decode($event->getItem()->core_images);

            if ($images->image_intro != '') {
                $thisImage = $images->image_intro;
            } elseif ($images->image_fulltext != '') {
                $thisImage = $images->image_fulltext;
            } else {
                $thisImage = $thisImageDefault;
            }
            $this->pluginNr = 1;
        } elseif ($view == 'contact' && $this->pluginNr == 0) {
            $thisTitle = $event->getItem()->name;
            $thisDescription = $event->getItem()->con_position;
            $thisImage = $event->getItem()->image;
            $this->pluginNr = 1;
        } elseif ($view == 'article' && $event->getItem()->id && $this->pluginNr == 0) {
            $article_page_title = $event->getItem()->params['article_page_title'];
            $menu_metasesc = $app->getParams()->get('menu-meta_description');
            $metadesc = $event->getItem()->metadesc;
            $introtext = $event->getItem()->introtext;
            $fulltext = $event->getItem()->fulltext;
            $images = json_decode($event->getItem()->images);
            $thisTitle = $article_page_title != '' ? $article_page_title : $event->getItem()->title;

            if (isset($menu_metasesc) && $menu_metasesc != '') {
                $thisDescription = $menu_metasesc;
            } elseif ($metadesc != '') {
                $thisDescription = $metadesc;
            } elseif ($introtext != '') {
                $thisDescription = $introtext;
            } else {
                $thisDescription = $fulltext;
            }

            if ($images->image_intro != '') {
                $thisImage = $images->image_intro;
            } elseif ($images->image_fulltext != '') {
                $thisImage = $images->image_fulltext;
            } else {
                $thisImage = $thisImageDefault;
            }
            $this->pluginNr = 1;
        } else return;

        $this->renderTag('og:site_name', $thisSiteName, 'property');
        $this->renderTag('og:title', $thisTitle, 'property');
        $this->renderTag('og:description', $this->catStr($thisDescription), 'property');
        $this->renderTag('og:url', Uri::current(), 'property');
        $this->renderTag('og:image', $this->setImage($this->realCleanImageURL($thisImage)), 'property');
        $this->renderTag('og:type', $thisOgType, 'property');

        if ($twitterEnable == 1) {
            $this->renderTag('twitter:card', 'summary_large_image');
            $this->renderTag('twitter:site', $thisSiteName);
            $this->renderTag('twitter:title', $thisTitle);
            $this->renderTag('twitter:description', $this->catStr($thisDescription));
            $this->renderTag('twitter:image', $this->setImage($this->realCleanImageURL($thisImage)));
        }
    }
}
