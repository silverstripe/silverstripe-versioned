<?php

namespace SilverStripe\Versioned\Tests\DataDifferencerTest;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\ListboxField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Choices
 * @property int $ImageID
 * @property int $HasOneRelationID
 * @method Image Image()
 * @method HasOneRelationObject HasOneRelation()
 * @mixin Versioned
 */
class TestObject extends DataObject implements TestOnly
{
    private static $table_name = 'DataDifferencerTest_Object';

    private static $extensions = [
        Versioned::class
    ];

    private static $db = [
        'Choices' => "Varchar",
    ];

    private static $has_one = [
        'Image' => Image::class,
        'HasOneRelation' => HasOneRelationObject::class
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $choices = [
            'a' => 'a',
            'b' => 'b',
            'c' => 'c',
        ];
        $listField = new ListboxField('Choices', 'Choices', $choices);
        $fields->push($listField);

        return $fields;
    }
}
