<?php


namespace SilverStripe\Versioned\Tests\GraphQL\Fake;

use GraphQL\Type\Definition\ResolveInfo;

// GraphQL dependency is optional in versioned,
// and the follow implementation relies on existence of this class (in GraphQL v4)
if (!class_exists(ResolveInfo::class)) {
    return;
}

class FakeResolveInfo extends ResolveInfo
{
    public function __construct()
    {
    }
}
