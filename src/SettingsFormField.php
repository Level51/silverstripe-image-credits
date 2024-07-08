<?php

namespace Level51\ImageCredits;

use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

class SettingsFormField extends CompositeField
{
    public function __construct($name, $title = null)
    {
        $children = FieldList::create(
            [
                NumericField::create(
                    'TextMargin',
                    _t(__CLASS__ . '.TextMargin', 'Text Margin'),
                ),
                NumericField::create(
                    'BoxPadding',
                    _t(__CLASS__ . '.BoxPadding', 'Box Padding'),
                ),
                NumericField::create(
                    'FontSize',
                    _t(__CLASS__ . '.FontSize', 'Font Size'),
                ),
                DropdownField::create(
                    'Position',
                    _t(__CLASS__ . '.Position', 'Position'),
                    ImageExtension::getPositionOptions(),
                )->setHasEmptyDefault(true),
            ],
        );

        // TODO add color picker for font and box background color

        parent::__construct($children);

        $this->setName($name);
        if ($title) {
            $this->setTitle($title);
        }
    }

    public function setValue($value, $data = null): void
    {
        parent::setValue($value, $data);

        if ($this->value) {
            foreach (json_decode($this->value) as $fieldName => $fieldValue) {
                if ($field = $this->fieldByName($fieldName)) {
                    $field->setValue($fieldValue);
                }
            }
        }
    }

    public function hasData(): bool
    {
        return true;
    }

    public function dataValue(): string
    {
        $data = [];
        foreach ($this->getChildren() as $child) {
            if ($child->dataValue()) {
                $data[$child->getName()] = $child->dataValue();
            }
        }

        return json_encode($data);
    }
}
