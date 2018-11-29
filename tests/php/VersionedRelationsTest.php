<?php
namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Tests\VersionedRelationsTest\ChildContainerObject;
use SilverStripe\Versioned\Tests\VersionedRelationsTest\ChildObject;
use SilverStripe\Versioned\Tests\VersionedRelationsTest\ParentObject;
use SilverStripe\Versioned\Versioned;

/**
 * This test suite is intended to test various interactions between versioned object that are related to one another
 * usually using the "owns" configuration
 */
class VersionedRelationsTest extends SapphireTest
{
    protected $usesDatabase = true;

    public static $extra_dataobjects = [
        ParentObject::class,
        ChildContainerObject::class,
        ChildObject::class,
    ];

    public function testChildDraftVersionPropagateToParent()
    {
        $parent = ParentObject::create();
        $parent->Name = 'Parent';
        $parent->write();

        $this->assertCount(
            1,
            Versioned::get_all_versions(ParentObject::class, $parent->ID),
            'There is not exactly 1 version of the child container'
        );

        $parent->ChildContainer->write();

        $this->assertCount(
            2,
            Versioned::get_all_versions(ParentObject::class, $parent->ID),
            'Writing (creating) an owned relation did not create a new version of the parent object'
        );

        $parent->ChildContainer->Text = 'Something';
        $parent->ChildContainer->write();

        $this->assertCount(
            3,
            Versioned::get_all_versions(ParentObject::class, $parent->ID),
            'Writing (updating) an owned relation did not create a new version of the parent object'
        );

        $child = ChildObject::create();
        $child->Name = 'Child';

        $parent->ChildContainer->Children->add($child);

        $this->assertCount(
            4,
            Versioned::get_all_versions(ParentObject::class, $parent->ID),
            'Adding an relation to an object that owns that relation that is a relation owned by the parent object did '
            . 'not create a new version'
        );
    }
}
