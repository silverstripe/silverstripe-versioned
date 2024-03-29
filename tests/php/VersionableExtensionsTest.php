<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\ORM\DB;
use SilverStripe\Dev\SapphireTest;

class VersionableExtensionsTest extends SapphireTest
{
    protected static $fixture_file = 'VersionableExtensionsFixtures.yml';

    protected static $extra_dataobjects = [
        VersionableExtensionsTest\TestObject::class,
    ];

    public function testTablesAreCreated()
    {
        $tables = DB::table_list();

        $check = [
            'versionableextensionstest_dataobject_test1_live', 'versionableextensionstest_dataobject_test2_live', 'versionableextensionstest_dataobject_test3_live',
            'versionableextensionstest_dataobject_test1_versions', 'versionableextensionstest_dataobject_test2_versions', 'versionableextensionstest_dataobject_test3_versions'
        ];

        // Check that the right tables exist
        foreach ($check as $tableName) {
            $this->assertContains($tableName, array_keys($tables ?? []), 'Contains table: '.$tableName);
        }
    }
}
