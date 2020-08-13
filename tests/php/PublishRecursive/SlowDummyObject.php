<?php

namespace SilverStripe\Versioned\Tests\PublishRecursive;

use Exception;
use ReflectionProperty;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Versioned\Versioned;

/**
 * Class SlowDummyObject
 *
 * @method SlowDummyParent Parent()
 * @package SilverStripe\Versioned\Tests\PublishRecursive
 */
class SlowDummyObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'SlowDummyObject';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $belongs_to = [
        'Parent' => SlowDummyParent::class . '.NestedObject',
    ];

    /**
     * @var array
     */
    private static $owned_by = [
        'Parent',
    ];

    protected function onBeforeWrite()
    {
        $this->emulateSleep(2);
        parent::onBeforeWrite();
    }

    /**
     * @param int $seconds
     * @throws Exception
     */
    private function emulateSleep($seconds)
    {
        if (Versioned::get_stage() !== Versioned::LIVE) {
            return;
        }

        if ($this->getMockNow() !== null) {
            return;
        }

        $now = DBDatetime::now();

        /** @var DBDatetime $now */
        $now = DBField::create_field('Datetime', $now->getTimestamp() + $seconds);
        DBDatetime::set_mock_now($now);
    }

    /**
     * @return DBDatetime|null
     * @throws ReflectionException
     */
    private function getMockNow()
    {
        $propertyMockNow = new ReflectionProperty(DBDatetime::class, 'mock_now');
        $propertyMockNow->setAccessible(true);

        return $propertyMockNow->getValue();
    }
}
