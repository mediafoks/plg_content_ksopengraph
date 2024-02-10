<?php

/**
 * @version    1.1.2
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
use Joomla\CMS\Event\Content\AfterDisplayEvent;

final class KsOpenGraph extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected $allowLegacyListeners = false;

    public $pluginNr = 0;
    public $twitterEnable = 0;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterDisplay' => 'onContentAfterDisplay',
        ];
    }

    function realCleanImageUrl($img)
    {

        $imgClean = HTMLHelper::cleanImageURL($img);
        if ($imgClean->url != '') {
            $img =  $imgClean->url;
        }
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

    public function renderTag($name, $value, $type = 1)
    {
        $app = $this->getApplication();
        $document = $app->getDocument();

        $value = strip_tags(html_entity_decode($value));

        // OG
        if ($type == 1) {
            $document->setMetadata(htmlspecialchars($name, ENT_COMPAT, 'UTF-8'), htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
        } else {
            $attributes = '';
            if ($name == 'og:image') $attributes = ' itemprop="image"';
            $document->addCustomTag('<meta property="' . htmlspecialchars($name, ENT_COMPAT, 'UTF-8') . '"' . $attributes . ' content="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '" />');
        }

        // Tweet with cards
        if ($this->twitterEnable == 1) {
            if ($name == 'og:title') {
                $document->setMetadata('twitter:title', htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
            }
            if ($name == 'og:description') {
                $document->setMetadata('twitter:description', htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
            }
            if ($name == 'og:image') {
                $document->setMetadata('twitter:image', htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
            }
        }
    }

    public function onContentAfterDisplay(AfterDisplayEvent $event): void
    {
        $app = $this->getApplication();
        $config = Factory::getConfig();
        $document = $app->getDocument();
        $view = $app->input->get('view'); // article, category, featured

        if (!$app->isClient('site')) return; // если это не фронтэнд, то прекращаем работу
        if ((int)$this->pluginNr > 0) return; // Second instance in featured view or category view

        $thisTitle = '';
        $thisDescription = '';
        $thisImage = '';
        $thisOgType = Uri::current() != Uri::base() ? 'article' : 'website';
        $thisImageDefault = $this->params->get('image_default');

        $this->twitterEnable = $this->params->get('twitter_enable', 0);
        if ($this->twitterEnable == 1) {
            $this->renderTag('twitter:card', 'summary_large_image', 1);
            $this->renderTag('twitter:site', $config->get('sitename'), 1);
        }

        if ($view == 'featured' && $this->pluginNr == 0) {
            $thisTitle = $document->title;
            $menu_metasesc = $app->getParams()->get('menu-meta_description');
            $thisDescription = isset($menu_metasesc) && $menu_metasesc != '' ? $menu_metasesc : $document->description;
            $thisImage = $thisImageDefault;
            $this->pluginNr = 1;
        } elseif ($view == 'category' && $this->pluginNr == 0) {

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
        } else {
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
        }

        $this->renderTag('og:site_name', $config->get('sitename'), $type);
        $this->renderTag('og:title', $thisTitle, $type);
        $this->renderTag('og:description', mb_strimwidth(strip_tags($thisDescription), 0, 300, "..."), $type);
        $this->renderTag('og:url', Uri::current(), $type);
        $this->renderTag('og:image', $this->setImage($this->realCleanImageURL($thisImage)), $type);
        $this->renderTag('og:type', $thisOgType, $type);
    }
}
