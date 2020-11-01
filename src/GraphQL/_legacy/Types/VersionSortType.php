<?php


namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Pagination\SortDirectionTypeCreator;
use SilverStripe\GraphQL\TypeCreator;

if (!class_exists(TypeCreator::class)) {
    return;
}

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
