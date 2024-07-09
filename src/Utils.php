<?php

namespace Level51\ImageCredits;

use SilverStripe\Assets\Image;
use SilverStripe\View\TemplateGlobalProvider;

class Utils implements TemplateGlobalProvider
{
    public static function getImageWithCredits()
    {
        return Image::get()->where('Credits IS NOT NULL');
    }

    public static function get_template_global_variables()
    {
        return [
            'ImageWithCredits' => 'getImageWithCredits',
        ];
    }
}
