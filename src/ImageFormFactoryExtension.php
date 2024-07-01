<?php

namespace Level51\ImageCredits;

use SilverStripe\AssetAdmin\Forms\ImageFormFactory;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

/**
 * Extension for the image form factory.
 *
 * Adds a new text field for the credits to the edit form.
 *
 * @property ImageFormFactory $owner
 */
class ImageFormFactoryExtension extends Extension
{
    public function updateFormFields(FieldList $fields, $controller, $formName, $context)
    {
        $image = $context['Record'] ?? null;

        if ($image && $image->appCategory() === 'image') {
            $creditsField = TextField::create('Credits', _t(ImageExtension::class . '.Credits', 'Credits'));
            $titleField = $fields->fieldByName('Editor.Details.Title');

            if ($titleField) {
                if ($titleField->isReadonly()) {
                    $creditsField = $creditsField->performReadonlyTransformation();
                }

                $fields->insertAfter(
                    'Title',
                    $creditsField,
                );
            }
        }
    }
}
