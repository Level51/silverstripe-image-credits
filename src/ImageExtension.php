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
    public const string POSITION_BOTTOM_LEFT = 'bottom_left';
    public const string POSITION_BOTTOM_RIGHT = 'bottom_right';
    public const string POSITION_BOTTOM_CENTER = 'bottom_center';

    private static array $db = [
        'Credits'         => 'Varchar(255)',
        'CreditsSettings' => 'Text',
    ];

    public static function getPositionOptions(): array
    {
        return [
            self::POSITION_BOTTOM_LEFT   => _t(__CLASS__ . '.BottomLeft', 'Bottom Left'),
            self::POSITION_BOTTOM_CENTER => _t(__CLASS__ . '.BottomCenter', 'Bottom Center'),
            self::POSITION_BOTTOM_RIGHT  => _t(__CLASS__ . '.BottomRight', 'Bottom Right'),
        ];
    }

    public function updateCMSFields(FieldList $fields): void
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

    public function updateFieldLabels(&$labels): void
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

    private function getPositionOption(array|null $creditsSettings): string
    {
        $options = self::getPositionOptions();

        if ($creditsSettings && isset($creditsSettings['Position']) && isset($options[$creditsSettings['Position']])) {
            return $creditsSettings['Position'];
        }

        $position = Config::inst()->get(__CLASS__, 'position');

        if (isset($options[$position])) {
            return $position;
        }

        return self::POSITION_BOTTOM_RIGHT;
    }

    private function getParsedCreditsSettings(): ?array
    {
        if ($this->owner->CreditsSettings) {
            return json_decode($this->owner->CreditsSettings, true);
        }

        return null;
    }

    /**
     * Get all relevant settings used to put the credits on the owner image.
     *
     * @return array
     */
    private function getFinalCreditsSettings(): array
    {
        $config = Config::forClass(__CLASS__);
        $creditsSettings = $this->getParsedCreditsSettings();

        // TODO maintainable font (at least provide a few options)?
        $fontPath = realpath(dirname(__DIR__) . '/assets/fonts') . '/arial/ARIAL.TTF';

        return [
            'textMargin'    => $creditsSettings['TextMargin'] ?? $config->get('text_margin'),
            'boxPadding'    => $creditsSettings['BoxPadding'] ?? $config->get('box_padding'),
            'fontPath'      => $fontPath,
            'fontSize'      => $creditsSettings['FontSize'] ?? $config->get('font_size'),
            'fontColor'     => $config->get('font_color'),
            'boxBackground' => $config->get('box_background'),
            'position'      => $this->getPositionOption($creditsSettings),
        ];
    }

    private function getTextPosition($settings, $imageWidth, $imageHeight, $fontBoxSize): array
    {
        $y = intval($imageHeight - $settings['textMargin']);
        $boxY1 = intval($y - $fontBoxSize['height'] - $settings['boxPadding']);
        $boxY2 = intval($y + $settings['boxPadding']);

        switch ($settings['position']) {
            case self::POSITION_BOTTOM_LEFT:
                $x = intval($settings['textMargin']);

                return [
                    'x'      => $x,
                    'y'      => $y,
                    'align'  => 'left',
                    'valign' => 'bottom',
                    'box'    => [
                        'x1' => intval($x - $settings['boxPadding']),
                        'y1' => $boxY1,
                        'x2' => intval($x + $fontBoxSize['width'] + $settings['boxPadding']),
                        'y2' => $boxY2,
                    ],
                ];
            case self::POSITION_BOTTOM_CENTER:
                $x = intval($imageWidth / 2);

                return [
                    'x'      => $x,
                    'y'      => $y,
                    'align'  => 'center',
                    'valign' => 'bottom',
                    'box'    => [
                        'x1' => intval($x - ($fontBoxSize['width'] / 2) - $settings['boxPadding']),
                        'y1' => $boxY1,
                        'x2' => intval($x + ($fontBoxSize['width'] / 2) + $settings['boxPadding']),
                        'y2' => $boxY2,
                    ],
                ];
            default:
                $x = intval($imageWidth - $settings['textMargin']);

                return [
                    'x'      => $x,
                    'y'      => $y,
                    'align'  => 'right',
                    'valign' => 'bottom',
                    'box'    => [
                        'x1' => intval($x - $fontBoxSize['width'] - $settings['boxPadding']),
                        'y1' => $boxY1,
                        'x2' => intval($x + $settings['boxPadding']),
                        'y2' => $boxY2,
                    ],
                ];
        }
    }

    public function AddCredits(): DBFile|Image|null
    {
        $original = $this->owner;
        $credits = $original->Credits;
        $settings = $this->getFinalCreditsSettings();

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

                    $boxSize = $this->getBoxSize($credits, $settings['fontPath'], $settings['fontSize']);

                    $positions = $this->getTextPosition($settings, $imageWidth, $imageHeight, $boxSize);

                    $resource->text($credits, $positions['x'], $positions['y'], function ($font) use ($credits, $resource, $settings, $positions, $boxSize) {
                        /** @var AbstractFont $font */
                        $font->file($settings['fontPath']);
                        $font->size($settings['fontSize']);
                        $font->align($positions['align']);
                        $font->valign($positions['valign']);
                        $font->color($settings['fontColor']);

                        if ($boxSize['width'] && $boxSize['height']) {
                            $resource->rectangle(
                                $positions['box']['x1'],
                                $positions['box']['y1'],
                                $positions['box']['x2'],
                                $positions['box']['y2'],
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
