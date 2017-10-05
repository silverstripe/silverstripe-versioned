<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Tests\Storage\AssetStoreTest\TestAssetStore;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\DataDifferencer;
use SilverStripe\Versioned\Versioned;

/**
 * @skipUpgrade
 */
class DataDifferencerTest extends SapphireTest
{
    protected static $fixture_file = 'DataDifferencerTest.yml';

    protected static $extra_dataobjects = [
        DataDifferencerTest\TestObject::class,
        DataDifferencerTest\HasOneRelationObject::class
    ];

    public function setUp()
    {
        parent::setUp();

        Versioned::set_stage(Versioned::DRAFT);

        // Set backend root to /DataDifferencerTest
        TestAssetStore::activate('DataDifferencerTest');

        // Create a test files for each of the fixture references
        $files = File::get()->exclude('ClassName', Folder::class);
        /** @var File $file */
        foreach ($files as $file) {
            $fromPath = __DIR__ . '/DataDifferencerTest/images/' . $file->Name;
            $file->setFromLocalFile($fromPath);
        }
    }

    public function tearDown()
    {
        TestAssetStore::reset();
        parent::tearDown();
    }

    /**
     * Assert $needle exists in $haystack, but ignores all whitespaces
     *
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    public static function assertContainsIgnoreWhitespace($needle, $haystack, $message = '')
    {
        $needle = preg_replace('#\s+#', '', $needle);
        $haystack = preg_replace('#\s+#', '', $haystack);
        return parent::assertContains($needle, $haystack, $message);
    }

    public function testArrayValues()
    {
        /** @var DataDifferencerTest\TestObject $obj1 */
        $obj1 = $this->objFromFixture(DataDifferencerTest\TestObject::class, 'obj1');
        $beforeVersion = $obj1->Version;
        // create a new version
        $obj1->Choices = 'a';
        $obj1->write();
        $afterVersion = $obj1->Version;
        $obj1v1 = Versioned::get_version(DataDifferencerTest\TestObject::class, $obj1->ID, $beforeVersion);
        $obj1v2 = Versioned::get_version(DataDifferencerTest\TestObject::class, $obj1->ID, $afterVersion);
        $differ = new DataDifferencer($obj1v1, $obj1v2);
        $obj1Diff = $differ->diffedData();
        // TODO Using getter would split up field again, bug only caused by simulating
        // an array-based value in the first place.
        $this->assertContainsIgnoreWhitespace('<ins>a</ins><del>a,b</del>', $obj1Diff->getField('Choices'));
    }

    public function testCast()
    {
        /** @var DataDifferencerTest\TestObject $obj1 */
        $obj1 = $this->objFromFixture(DataDifferencerTest\TestObject::class, 'obj1');
        $v1 = $obj1->Version;
        // Empty value
        $obj1->Choices = '';
        $obj1->write();
        $v2 = $obj1->Version;
        $obj1v1 = Versioned::get_version(DataDifferencerTest\TestObject::class, $obj1->ID, $v1);
        $obj1v2 = Versioned::get_version(DataDifferencerTest\TestObject::class, $obj1->ID, $v2);
        $differ = new DataDifferencer($obj1v1, $obj1v2);
        $obj1Diff = $differ->diffedData();
        $this->assertContainsIgnoreWhitespace('<del>a,b</del>', $obj1Diff->getField('Choices'));

        // Set html value and check <ins>
        $obj1->Choices = '<strong>Value</strong>';
        $obj1->write();
        $v3 = $obj1->Version;
        $obj1v3 = Versioned::get_version(DataDifferencerTest\TestObject::class, $obj1->ID, $v3);
        $differ = new DataDifferencer($obj1v2, $obj1v3);
        $obj1Diff = $differ->diffedData();
        $this->assertContainsIgnoreWhitespace('<ins>&lt;strong&gt;Value&lt;/strong&gt;</ins>', $obj1Diff->getField('Choices'));

        // Diff between plain text and html
        $differ = new DataDifferencer($obj1v1, $obj1v3);
        $obj1Diff = $differ->diffedData();
        $this->assertContainsIgnoreWhitespace(
            '<ins>&lt;strong&gt;Value&lt;/strong&gt;</ins>  <del>a,b</del>',
            $obj1Diff->getField('Choices')
        );
    }

    public function testHasOnes()
    {
        /** @var DataDifferencerTest\TestObject $obj1 */
        $obj1 = $this->objFromFixture(DataDifferencerTest\TestObject::class, 'obj1');
        /** @var Image $image1 */
        $image1 = $this->objFromFixture(Image::class, 'image1');
        /** @var Image $image2 */
        $image2 = $this->objFromFixture(Image::class, 'image2');
        /** @var DataDifferencerTest\HasOneRelationObject $relobj2 */
        $relobj2 = $this->objFromFixture(DataDifferencerTest\HasOneRelationObject::class, 'relobj2');

        // create a new version
        $beforeVersion = $obj1->Version;
        $obj1->ImageID = $image2->ID;
        $obj1->HasOneRelationID = $relobj2->ID;
        $obj1->write();
        $afterVersion = $obj1->Version;
        $this->assertNotEquals($beforeVersion, $afterVersion);
        /** @var DataDifferencerTest\TestObject $obj1v1 */
        $obj1v1 = Versioned::get_version(DataDifferencerTest\TestObject::class, $obj1->ID, $beforeVersion);
        /** @var DataDifferencerTest\TestObject $obj1v2 */
        $obj1v2 = Versioned::get_version(DataDifferencerTest\TestObject::class, $obj1->ID, $afterVersion);
        $differ = new DataDifferencer($obj1v1, $obj1v2);
        $obj1Diff = $differ->diffedData();

        /** @skipUpgrade */
        $this->assertContainsIgnoreWhitespace($image1->Name, $obj1Diff->getField('Image'));
        /** @skipUpgrade */
        $this->assertContainsIgnoreWhitespace($image2->Name, $obj1Diff->getField('Image'));
        $this->assertContainsIgnoreWhitespace(
            '<ins>obj2</ins>  <del>obj1</del>',
            $obj1Diff->getField('HasOneRelationID')
        );
    }
}
