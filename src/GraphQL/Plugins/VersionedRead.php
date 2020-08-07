<?php


namespace SilverStripe\Versioned\GraphQL\Plugins;


use SilverStripe\Core\Extensible;
use SilverStripe\GraphQL\Schema\Field\ModelQuery;
use SilverStripe\GraphQL\Schema\Interfaces\ModelQueryPlugin;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\Versioned\GraphQL\Resolvers\VersionedResolver;
use SilverStripe\Versioned\Versioned;

if (!interface_exists(ModelQueryPlugin::class)) {
    return;
}

class VersionedRead implements ModelQueryPlugin
{
    const IDENTIFIER = 'readVersion';

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * @param ModelQuery $query
     * @param Schema $schema
     * @param array $config
     */
    public function apply(ModelQuery $query, Schema $schema, array $config = []): void
    {
        $class = $query->getModel()->getSourceClass();
        if (!Extensible::has_extension($class, Versioned::class)) {
            return;
        }

        $query->addResolverAfterware([VersionedResolver::class, 'resolveVersionedRead']);
        $query->addArg('versioning', 'VersionedInputType');
    }
}
