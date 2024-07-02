<?php

namespace Level51\ImageCredits;

use Intervention\Image\AbstractFont;
use Intervention\Image\Gd\Font as GdFont;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Image_Backend;
use SilverStripe\Assets\Storage\DBFile;
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
        // TODO maintainable font (at least provide a few options)?
        $fontPath = realpath(dirname(__DIR__) . '/assets/fonts') . '/arial/ARIAL.TTF';

        // TODO maintainable settings like size, font color, position, box background color...?

        return [
            'textMargin'    => 10,
            'boxPadding'    => 10,
            'fontPath'      => $fontPath,
            'fontSize'      => 30,
            'fontColor'     => '#000000',
            'boxBackground' => 'rgba(255, 255, 255, 0.7)',
        ];
    }

    public function AddCredits(): DBFile|Image|null
    {
        $original = $this->owner;
        $credits = $original->Credits;
        $variant = $original->variantName(__FUNCTION__, $credits, time());

        if (!$original->exists() || !$original->Credits) {
            return $original;
        }

        return $this->owner->manipulateImage(
            $variant,
            function (Image_Backend $backend) use ($credits, $original) {
                if (!($resource = $backend->getImageResource())) {
                    return null;
                }

                if ($resource instanceof \Intervention\Image\Image) {
                    $settings = $this->getCreditsSettings();

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

                        /* Set the content again to work around a bug in intervention/image < v3 (which is still used by Silverstripe)
                         * @see https://github.com/Intervention/image/issues/1126
                         */
                        $font->text($credits);
                    });
                }

                $clone = clone $backend;
                $clone->setImageResource($resource);
                return $clone;
            },
        );
    }
}
