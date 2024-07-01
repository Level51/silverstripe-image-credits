<?php

namespace Level51\ImageCredits;

use SilverStripe\Assets\Image;
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
}
