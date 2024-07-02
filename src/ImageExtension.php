<?php

namespace Level51\ImageCredits;

use Intervention\Image\AbstractFont;
use Intervention\Image\Gd\Font as GdFont;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Image_Backend;
use SilverStripe\Assets\Storage\DBFile;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;

/**
 * Extension for the Image model.
 *
 * @property Image $owner
 */
class ImageExtension extends DataExtension
{
    private static $db = [
        'Credits' => 'Varchar(255)',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);

        $fields->insertAfter(
            'Title',
            TextField::create(
                'Credits',
                $this->owner->fieldLabel('Credits'),
            ),
        );
    }

    public function updateFieldLabels(&$labels)
    {
        parent::updateFieldLabels($labels);
        $labels['Credits'] = _t(__CLASS__ . '.Credits', 'Credits');
    }

    /**
     * Get the box size of the given text content.
     *
     * Depends on the used font and size.
     * Always use GD for this task (even if Imagemagick is used for everything else) as
     * Imagemagick returns a wrong "height" value.
     *
     * @param string $content
     * @param string $fontPath
     * @param int    $fontSize
     * @return array
     */
    private function getBoxSize(string $content, string $fontPath, int $fontSize): array
    {
        $font = new GdFont($content);
        $font->file($fontPath);
        $font->size($fontSize);

        $boxSize = $font->getBoxSize();

        return [
            'width'  => $boxSize['width'] ?? 0,
            'height' => $boxSize['height'] ?? 0,
        ];
    }

    /**
     * Get all relevant settings used to put the credits on the owner image.
     *
     * @return array
     */
    private function getCreditsSettings(): array
    {
        $config = Config::forClass(__CLASS__);

        // TODO maintainable font (at least provide a few options)?
        $fontPath = realpath(dirname(__DIR__) . '/assets/fonts') . '/arial/ARIAL.TTF';

        // TODO maintainable position

        return [
            'textMargin'    => $config->get('text_margin'),
            'boxPadding'    => $config->get('box_padding'),
            'fontPath'      => $fontPath,
            'fontSize'      => $config->get('font_size'),
            'fontColor'     => $config->get('font_color'),
            'boxBackground' => $config->get('box_background'),
        ];
    }

    public function AddCredits(): DBFile|Image|null
    {
        $original = $this->owner;
        $credits = $original->Credits;
        $settings = $this->getCreditsSettings();

        $variantNameParams = [__FUNCTION__, $credits, md5(implode('::', array_values($settings)))];

        // Allow to force image rebuild by adding the current timestamp to the variant name
        if (Config::inst()->get(__CLASS__, 'force_rebuild')) {
            $variantNameParams[] = time();
        }

        $variant = $original->variantName(...$variantNameParams);

        if (!$original->exists() || !$original->Credits) {
            return $original;
        }

        return $this->owner->manipulateImage(
            $variant,
            function (Image_Backend $backend) use ($credits, $original, $settings) {
                if (!($resource = $backend->getImageResource())) {
                    return null;
                }

                if ($resource instanceof \Intervention\Image\Image) {
                    $imageWidth = $original->getWidth();
                    $imageHeight = $original->getHeight();

                    $x = $imageWidth - $settings['textMargin'];
                    $y = $imageHeight - $settings['textMargin'];

                    $resource->text($credits, $x, $y, function ($font) use ($credits, $resource, $x, $y, $settings) {
                        /** @var AbstractFont $font */
                        $font->file($settings['fontPath']);
                        $font->size($settings['fontSize']);
                        $font->align('right');
                        $font->valign('bottom');
                        $font->color($settings['fontColor']);

                        $boxSize = $this->getBoxSize($credits, $settings['fontPath'], $settings['fontSize']);

                        if ($boxSize['width'] && $boxSize['height']) {
                            $resource->rectangle(
                                $x - $boxSize['width'] - $settings['boxPadding'],
                                $y - $boxSize['height'] - $settings['boxPadding'],
                                $x + $settings['boxPadding'],
                                $y + $settings['boxPadding'],
                                function ($draw) use ($settings) {
                                    $draw->background($settings['boxBackground']);
                                },
                            );
                        }
                    });
                }

                $clone = clone $backend;
                $clone->setImageResource($resource);
                return $clone;
            },
        );
    }
}
