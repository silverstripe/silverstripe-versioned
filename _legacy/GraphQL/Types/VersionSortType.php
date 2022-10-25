<?php


namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\Dev\Deprecation;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Pagination\SortDirectionTypeCreator;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\TypeCreator;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(TypeCreator::class)) {
    return;
}

/**
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class VersionSortType extends TypeCreator
{
    /**
     * @var static
     */
    private $type;

    /**
     * @var bool
     */
    protected $inputObject = true;

    /**
     * @return array
     */
    public function __construct(Manager $manager = null)
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
        parent::__construct($manager);
    }

    public function attributes()
    {
        return [
            'name' => 'VersionSortType',
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        return [
            'version' => [
                'type' => Injector::inst()->get(SortDirectionTypeCreator::class)->toType()
            ]
        ];
    }

    /**
     * @return static
     */
    public function toType()
    {
        if (!$this->type) {
            $this->type = parent::toType();
        }

        return $this->type;
    }
}
