<?php


namespace SilverStripe\Versioned\Tests\GraphQL\Fake;

use GraphQL\Type\Definition\ResolveInfo;

if (!class_exists(ResolveInfo::class)) {
    return;
}

class FakeResolveInfo extends ResolveInfo
{
    public function __construct()
    {
    }
}
