<?php
    /**
     * @package     DC Gallery
     * @subpackage  Content Plugin
     * @author      Design Cart
     * @copyright   Copyright (C) 2025 Design Cart. All rights reserved.
     * @license     GNU General Public License version 3 or later; see LICENSE.txt
     *
     * This file is part of DC Gallery.
     *
     * DC Gallery is free software: you can redistribute it and/or modify
     * it under the terms of the GNU General Public License as published by
     * the Free Software Foundation, either version 3 of the License, or
     * (at your option) any later version.
     *
     * DC Gallery is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
     * GNU General Public License for more details.
     *
     * You should have received a copy of the GNU General Public License
     * along with DC Gallery. If not, see <https://www.gnu.org/licenses/>.
    */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\String\StringHelper;

final class PlgContentDc_gallery extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private $instanceCounter = 0;

    /** @var array<string, bool> */
    private $assetFlags = [
        'base' => false,
        'swiper' => false,
        'macy' => false,
    ];

    public function onContentPrepare($context, &$article, &$params, $page = 0): void
    {
        if (empty($article->text) || strpos($article->text, '{dcgallery') === false) {
            return;
        }

        $pattern = '/\{dcgallery\s+([^}]*)\}/i';
        $article->text = (string) preg_replace_callback($pattern, function ($matches) {
            $attributes = $this->parseAttributes($matches[1] ?? '');

            return $this->renderGallery($attributes);
        }, $article->text);
    }

    private function parseAttributes(string $attributeString): array
    {
        $attributes = [];
        $regex = '/([a-zA-Z0-9_-]+)\s*=\s*([\'"“”])([^\'"“”]*)\2/';

        if (!preg_match_all($regex, $attributeString, $matches, PREG_SET_ORDER)) {
            return $attributes;
        }

        foreach ($matches as $match) {
            $key = StringHelper::strtolower(trim($match[1]));
            $value = trim($match[3]);
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    private function renderGallery(array $atts): string
    {
        $defaults = [
            'mode' => (string) $this->params->get('mode', 'normal'),
            'columns' => (string) $this->params->get('columns', '4'),
            'ratio' => (string) $this->params->get('ratio', '3:4'),
            'slide_ratio' => (string) $this->params->get('slide_ratio', '16:9'),
            'slides_visible' => (string) $this->params->get('slides_visible', '3'),
            'space' => (string) $this->params->get('space', '16'),
            'loop' => (string) $this->params->get('loop', '1'),
            'glightbox_loop' => (string) $this->params->get('glightbox_loop', '1'),
            'nav_bg' => (string) $this->params->get('nav_bg', '#111111'),
            'nav_color' => (string) $this->params->get('nav_color', '#ffffff'),
            'nav_opacity' => (string) $this->params->get('nav_opacity', '0.45'),
            'nav_hover_opacity' => (string) $this->params->get('nav_hover_opacity', '1'),
        ];

        $source = $atts['source'] ?? $atts['sourc'] ?? '';
        $source = trim($source, '/');

        if ($source === '') {
            return '<!-- ' . htmlspecialchars(Text::_('PLG_CONTENT_DC_GALLERY_ERR_MISSING_SOURCE'), ENT_QUOTES, 'UTF-8') . ' -->';
        }

        $options = array_merge($defaults, $atts);
        $images = $this->getImagesForSource($source);

        if ($images === []) {
            return '<!-- ' . htmlspecialchars(Text::sprintf('PLG_CONTENT_DC_GALLERY_ERR_NO_IMAGES', $source), ENT_QUOTES, 'UTF-8') . ' -->';
        }

        $this->instanceCounter++;
        $containerId = 'dc-gallery-' . $this->instanceCounter;
        $layoutMode = $this->normalizeLayoutMode($options['mode']);

        $this->loadAssetsForMode($layoutMode);

        $columns = $this->normalizeColumns($options['columns']);
        $cellAspect = $this->parseAspectRatio($options['ratio']);
        $slideAspect = $this->parseAspectRatio($options['slide_ratio']);
        $slidesVisible = $this->normalizeSlidesVisible($options['slides_visible']);
        $space = $this->normalizeSpace($options['space']);
        $loopSwiper = $this->toBool($options['loop'], true);
        $glightboxLoop = $this->toBool($options['glightbox_loop'], true);
        $navBg = $this->normalizeColor($options['nav_bg'], '#111111');
        $navColor = $this->normalizeColor($options['nav_color'], '#ffffff');
        $navOpacity = $this->normalizeOpacity($options['nav_opacity'], 0.45);
        $navHoverOpacity = $this->normalizeOpacity($options['nav_hover_opacity'], 1.0);

        $html = $this->renderLayout(
            $containerId,
            $layoutMode,
            $images,
            $columns,
            $cellAspect,
            $slideAspect,
            $navBg,
            $navColor,
            $navOpacity,
            $navHoverOpacity
        );

        $js = $this->buildInitScript(
            $containerId,
            $layoutMode,
            count($images),
            $columns,
            $slidesVisible,
            $space,
            $loopSwiper,
            $glightboxLoop
        );

        return $html . $js;
    }

    private function getImagesForSource(string $source): array
    {
        $baseFolder = trim((string) $this->params->get('base_folder', 'images/mygalleries'), '/');
        $fullRelative = trim($baseFolder . '/' . $source, '/');

        $siteRoot = rtrim(JPATH_ROOT, '/');
        $fullPath = Path::clean($siteRoot . '/' . $fullRelative);

        if (strpos($fullPath, $siteRoot) !== 0 || !is_dir($fullPath)) {
            return [];
        }

        $files = Folder::files($fullPath, '\.(jpe?g|png|gif|webp|avif)$', false, true);
        sort($files);

        $images = [];
        foreach ($files as $absoluteFile) {
            $relative = str_replace($siteRoot . '/', '', Path::clean($absoluteFile));
            $url = Uri::root() . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $title = pathinfo($absoluteFile, PATHINFO_FILENAME);

            $images[] = [
                'url' => $url,
                'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            ];
        }

        return $images;
    }

    private function loadAssetsForMode(string $mode): void
    {
        $doc = Factory::getDocument();
        $base = Uri::root(true) . '/plugins/content/dc_gallery';

        if (!$this->assetFlags['base']) {
            $doc->addStyleSheet($base . '/media/css/glightbox.min.css');
            $doc->addStyleSheet($base . '/media/css/dc_gallery.css');
            $doc->addScript($base . '/media/js/glightbox.min.js', ['version' => 'auto'], ['defer' => true]);
            $this->assetFlags['base'] = true;
        }

        $needsSwiper = !in_array($mode, ['normal', 'tiles'], true);
        if ($needsSwiper && !$this->assetFlags['swiper']) {
            $doc->addStyleSheet($base . '/media/swiper/swiper-bundle.min.css');
            $doc->addScript($base . '/media/swiper/swiper-bundle.min.js', ['version' => 'auto'], ['defer' => true]);
            $this->assetFlags['swiper'] = true;
        }

        if ($mode === 'tiles' && !$this->assetFlags['macy']) {
            $doc->addScript($base . '/media/js/macy.js', ['version' => 'auto'], ['defer' => true]);
            $this->assetFlags['macy'] = true;
        }
    }

    /**
     * @param array<int, array{url: string, title: string}> $images
     */
    private function renderLayout(
        string $containerId,
        string $mode,
        array $images,
        int $columns,
        string $cellAspectCss,
        string $slideAspectCss,
        string $navBg,
        string $navColor,
        float $navOpacity,
        float $navHoverOpacity
    ): string {
        $navStyle = '--dcg-nav-bg:' . $navBg . ';--dcg-nav-color:' . $navColor
            . ';--dcg-nav-opacity:' . $navOpacity . ';--dcg-nav-hover-opacity:' . $navHoverOpacity . ';';

        switch ($mode) {
            case 'normal':
                $style = '--dcg-cols:' . $columns . ';--dcg-aspect:' . $cellAspectCss . ';';
                $html = '<div id="' . $containerId . '" class="dc-gallery dc-gallery--normal" style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '">';
                foreach ($images as $image) {
                    $html .= '<div class="dcg-cell"><div class="dcg-cell-inner">'
                        . $this->renderGlightboxAnchor($image, $containerId, '') . '</div></div>';
                }
                $html .= '</div>';

                return $html;

            case 'tiles':
                $html = '<div id="' . $containerId . '" class="dc-gallery dc-gallery--tiles">';
                $html .= '<div class="dcg-macy-inner">';
                foreach ($images as $image) {
                    $html .= '<div class="dcg-macy-item">' . $this->renderGlightboxAnchor($image, $containerId, '') . '</div>';
                }
                $html .= '</div></div>';

                return $html;

            case 'thumbs':
                $style = '--dcg-slide-aspect:' . $slideAspectCss . ';' . $navStyle;
                $html = '<div id="' . $containerId . '" class="dc-gallery dc-gallery--thumbs" style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '">';
                $html .= '<div class="swiper dcg-swiper-main"><div class="swiper-wrapper" id="' . $containerId . '-main-wrapper">';
                foreach ($images as $image) {
                    $html .= '<div class="swiper-slide"><div class="dcg-slide-media" style="aspect-ratio:' . htmlspecialchars($slideAspectCss, ENT_QUOTES, 'UTF-8') . '">'
                        . $this->renderGlightboxAnchor($image, $containerId, 'dcg-slide-link') . '</div></div>';
                }
                $html .= '</div>';
                $html .= $this->renderSwiperNavigationButtons($containerId . '-main-wrapper');
                $html .= '<div class="swiper-pagination"></div>';
                $html .= '</div>';
                $html .= '<div class="swiper dcg-swiper-thumbs"><div class="swiper-wrapper">';
                foreach ($images as $image) {
                    $html .= '<div class="swiper-slide"><div class="dcg-thumb-square">'
                        . $this->renderGlightboxAnchor($image, $containerId, 'dcg-thumb-link') . '</div></div>';
                }
                $html .= '</div></div></div>';

                return $html;

            default:
                return $this->renderSwiperLayout($containerId, $mode, $images, $containerId, $slideAspectCss, $navStyle);
        }
    }

    /**
     * @param array{url: string, title: string} $image
     */
    private function renderGlightboxAnchor(array $image, string $galleryId, string $extraClass): string
    {
        $classes = trim('dcg-glightbox ' . $extraClass);
        $plainTitle = html_entity_decode($image['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $glightbox = 'title: ' . $plainTitle;

        return '<a href="' . $image['url'] . '" class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8')
            . '" data-gallery="' . htmlspecialchars($galleryId, ENT_QUOTES, 'UTF-8')
            . '" data-glightbox="' . htmlspecialchars($glightbox, ENT_QUOTES, 'UTF-8') . '">'
            . '<img src="' . $image['url'] . '" alt="' . $image['title'] . '" loading="lazy" width="800" height="600">'
            . '</a>';
    }

    /**
     * @param array<int, array{url: string, title: string}> $images
     */
    private function renderSwiperLayout(
        string $containerId,
        string $mode,
        array $images,
        string $galleryId,
        string $slideAspectCss,
        string $navStyle
    ): string {
        $modClass = 'dc-gallery--' . $mode;
        $html = '<div id="' . $containerId . '" class="dc-gallery ' . htmlspecialchars($modClass, ENT_QUOTES, 'UTF-8') . ' swiper dcg-swiper" style="' . htmlspecialchars($navStyle, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="swiper-wrapper" id="' . $containerId . '-wrapper">';
        foreach ($images as $image) {
            $html .= '<div class="swiper-slide"><div class="dcg-slide-media" style="aspect-ratio:' . htmlspecialchars($slideAspectCss, ENT_QUOTES, 'UTF-8') . '">'
                . $this->renderGlightboxAnchor($image, $galleryId, 'dcg-slide-link') . '</div></div>';
        }
        $html .= '</div>';
        $html .= $this->renderSwiperNavigationButtons($containerId . '-wrapper');
        $html .= '<div class="swiper-pagination"></div>';
        $html .= '</div>';

        return $html;
    }

    private function renderSwiperNavigationButtons(string $controlsId): string
    {
        $svg = '<svg class="swiper-navigation-icon" width="11" height="20" viewBox="0 0 11 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            . '<path d="M0.38296 20.0762C0.111788 19.805 0.111788 19.3654 0.38296 19.0942L9.19758 10.2796L0.38296 1.46497C0.111788 1.19379 0.111788 0.754138 0.38296 0.482966C0.654131 0.211794 1.09379 0.211794 1.36496 0.482966L10.4341 9.55214C10.8359 9.9539 10.8359 10.6053 10.4341 11.007L1.36496 20.0762C1.09379 20.3474 0.654131 20.3474 0.38296 20.0762Z" fill="currentColor"></path>'
            . '</svg>';

        return '<div class="swiper-button-prev" tabindex="0" role="button" aria-label="' . htmlspecialchars(Text::_('JPREV'), ENT_QUOTES, 'UTF-8')
            . '" aria-controls="' . htmlspecialchars($controlsId, ENT_QUOTES, 'UTF-8') . '">' . $svg . '</div>'
            . '<div class="swiper-button-next" tabindex="0" role="button" aria-label="' . htmlspecialchars(Text::_('JNEXT'), ENT_QUOTES, 'UTF-8')
            . '" aria-controls="' . htmlspecialchars($controlsId, ENT_QUOTES, 'UTF-8') . '">' . $svg . '</div>';
    }

    private function buildInitScript(
        string $containerId,
        string $mode,
        int $imageCount,
        int $columns,
        int $slidesVisible,
        int $space,
        bool $loopSwiper,
        bool $glightboxLoop
    ): string {
        $idJson = json_encode($containerId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
        $selJson = json_encode('#' . $containerId . ' .dcg-glightbox', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
        $gbLoop = $glightboxLoop ? 'true' : 'false';

        $bootStart = '(function(){function dcgReady(){return typeof GLightbox!=="undefined";}';
        $bootSwiper = 'function dcgSwiperReady(){return typeof Swiper!=="undefined";}';
        $bootMacy = 'function dcgMacyReady(){return typeof Macy!=="undefined";}';
        $glightboxJs = 'GLightbox({selector:' . $selJson . ',loop:' . $gbLoop . '});';

        $poll = 'function run(attempt){attempt=attempt||0;if(attempt>120)return;';
        $pollEnd = 'requestAnimationFrame(function(){run(attempt+1);});return;';

        switch ($mode) {
            case 'normal':
                return '<script>' . $bootStart
                    . $poll . 'if(!dcgReady()){' . $pollEnd . '}' . $glightboxJs . '}'
                    . 'run();})();</script>';

            case 'tiles':
                return '<script>' . $bootStart . $bootMacy
                    . $poll . 'if(!dcgReady()||!dcgMacyReady()){' . $pollEnd . '}'
                    . 'var root=document.getElementById(' . $idJson . ');if(!root)return;'
                    . 'var inner=root.querySelector(".dcg-macy-inner");if(!inner)return;'
                    . 'Macy({container:inner,trueOrder:true,waitForImages:true,columns:' . $columns . ',margin:' . $space . '});'
                    . $glightboxJs . '}'
                    . 'run();})();</script>';

            case 'slideshow':
                $loop = ($loopSwiper && $imageCount > 1) ? 'true' : 'false';

                return '<script>' . $bootStart . $bootSwiper
                    . $poll . 'if(!dcgReady()||!dcgSwiperReady()){' . $pollEnd . '}'
                    . 'var el=document.getElementById(' . $idJson . ');if(!el)return;'
                    . 'new Swiper(el,{slidesPerView:1,spaceBetween:' . $space . ',loop:' . $loop . ','
                    . 'pagination:{el:el.querySelector(".swiper-pagination"),clickable:true},'
                    . 'navigation:{nextEl:el.querySelector(".swiper-button-next"),prevEl:el.querySelector(".swiper-button-prev")},'
                    . 'preventClicks:false,preventClicksPropagation:false});'
                    . $glightboxJs . '}'
                    . 'run();})();</script>';

            case 'carousel':
                $loop = ($loopSwiper && $imageCount > $slidesVisible) ? 'true' : 'false';

                return '<script>' . $bootStart . $bootSwiper
                    . $poll . 'if(!dcgReady()||!dcgSwiperReady()){' . $pollEnd . '}'
                    . 'var el=document.getElementById(' . $idJson . ');if(!el)return;'
                    . 'new Swiper(el,{slidesPerView:' . $slidesVisible . ',spaceBetween:' . $space . ',loop:' . $loop . ','
                    . 'pagination:{el:el.querySelector(".swiper-pagination"),clickable:true},'
                    . 'navigation:{nextEl:el.querySelector(".swiper-button-next"),prevEl:el.querySelector(".swiper-button-prev")},'
                    . 'preventClicks:false,preventClicksPropagation:false});'
                    . $glightboxJs . '}'
                    . 'run();})();</script>';

            case 'coverflow':
                $loop = ($loopSwiper && $imageCount > 1) ? 'true' : 'false';

                return '<script>' . $bootStart . $bootSwiper
                    . $poll . 'if(!dcgReady()||!dcgSwiperReady()){' . $pollEnd . '}'
                    . 'var el=document.getElementById(' . $idJson . ');if(!el)return;'
                    . 'new Swiper(el,{effect:"coverflow",grabCursor:true,centeredSlides:true,'
                    . 'slidesPerView:"auto",spaceBetween:' . $space . ',loop:' . $loop . ','
                    . 'coverflowEffect:{rotate:50,stretch:0,depth:100,modifier:1,slideShadows:true},'
                    . 'pagination:{el:el.querySelector(".swiper-pagination"),clickable:true},'
                    . 'navigation:{nextEl:el.querySelector(".swiper-button-next"),prevEl:el.querySelector(".swiper-button-prev")},'
                    . 'preventClicks:false,preventClicksPropagation:false});'
                    . $glightboxJs . '}'
                    . 'run();})();</script>';

            case 'cards':
                $loop = ($loopSwiper && $imageCount > 1) ? 'true' : 'false';

                return '<script>' . $bootStart . $bootSwiper
                    . $poll . 'if(!dcgReady()||!dcgSwiperReady()){' . $pollEnd . '}'
                    . 'var el=document.getElementById(' . $idJson . ');if(!el)return;'
                    . 'new Swiper(el,{effect:"cards",grabCursor:true,loop:' . $loop . ','
                    . 'pagination:{el:el.querySelector(".swiper-pagination"),clickable:true},'
                    . 'navigation:{nextEl:el.querySelector(".swiper-button-next"),prevEl:el.querySelector(".swiper-button-prev")},'
                    . 'preventClicks:false,preventClicksPropagation:false});'
                    . $glightboxJs . '}'
                    . 'run();})();</script>';

            case 'thumbs':
                $loop = ($loopSwiper && $imageCount > 1) ? 'true' : 'false';

                return '<script>' . $bootStart . $bootSwiper
                    . $poll . 'if(!dcgReady()||!dcgSwiperReady()){' . $pollEnd . '}'
                    . 'var root=document.getElementById(' . $idJson . ');if(!root)return;'
                    . 'var tEl=root.querySelector(".dcg-swiper-thumbs");var mEl=root.querySelector(".dcg-swiper-main");'
                    . 'if(!tEl||!mEl)return;'
                    . 'var thumbs=new Swiper(tEl,{spaceBetween:10,slidesPerView:"auto",freeMode:true,watchSlidesProgress:true,'
                    . 'preventClicks:false,preventClicksPropagation:false});'
                    . 'new Swiper(mEl,{spaceBetween:' . $space . ',loop:' . $loop . ','
                    . 'navigation:{nextEl:mEl.querySelector(".swiper-button-next"),prevEl:mEl.querySelector(".swiper-button-prev")},'
                    . 'pagination:{el:mEl.querySelector(".swiper-pagination"),clickable:true},'
                    . 'thumbs:{swiper:thumbs},preventClicks:false,preventClicksPropagation:false});'
                    . $glightboxJs . '}'
                    . 'run();})();</script>';

            default:
                return '<script>' . $bootStart
                    . $poll . 'if(!dcgReady()){' . $pollEnd . '}' . $glightboxJs . '}'
                    . 'run();})();</script>';
        }
    }

    private function normalizeLayoutMode(string $mode): string
    {
        $mode = StringHelper::strtolower(trim($mode));
        $map = [
            'normal' => 'normal',
            'grid' => 'normal',
            'standard' => 'normal',
            'tiles' => 'tiles',
            'macy' => 'tiles',
            'masonry' => 'tiles',
            'slideshow' => 'slideshow',
            'slider' => 'slideshow',
            'carousel' => 'carousel',
            'coverflow' => 'coverflow',
            'effect_coverflow' => 'coverflow',
            'effect-coverflow' => 'coverflow',
            'cards' => 'cards',
            'effect_cards' => 'cards',
            'effect-cards' => 'cards',
            'thumbs' => 'thumbs',
            'thumbs_gallery' => 'thumbs',
            'thumbs-gallery' => 'thumbs',
        ];

        return $map[$mode] ?? 'normal';
    }

    private function parseAspectRatio(string $ratio): string
    {
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*:\s*(\d+(?:\.\d+)?)$/', trim($ratio), $m)) {
            return '3 / 4';
        }

        $w = (float) $m[1];
        $h = (float) $m[2];

        if ($w <= 0 || $h <= 0) {
            return '3 / 4';
        }

        return $w . ' / ' . $h;
    }

    private function normalizeColumns($value): int
    {
        $n = (int) $value;

        return max(1, min(12, $n < 1 ? 4 : $n));
    }

    private function normalizeSlidesVisible($value): int
    {
        $n = (int) $value;

        return max(1, min(12, $n < 1 ? 3 : $n));
    }

    private function normalizeSpace($value): int
    {
        $n = (int) $value;

        return max(0, min(80, $n < 0 ? 16 : $n));
    }

    private function normalizeColor($value, string $fallback): string
    {
        $color = trim((string) $value);

        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return $color;
        }

        return $fallback;
    }

    private function normalizeOpacity($value, float $fallback): float
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        $opacity = (float) $value;

        if ($opacity < 0) {
            return 0.0;
        }

        if ($opacity > 1) {
            return 1.0;
        }

        return $opacity;
    }

    private function toBool($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = StringHelper::strtolower((string) $value);

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
