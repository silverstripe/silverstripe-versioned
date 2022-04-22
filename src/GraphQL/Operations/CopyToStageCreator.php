<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Field\ModelMutation;
use SilverStripe\GraphQL\Schema\Interfaces\ModelOperation;
use SilverStripe\GraphQL\Schema\Interfaces\OperationCreator;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaModelInterface;
use SilverStripe\Versioned\GraphQL\Resolvers\VersionedResolver;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ViewableData;

// GraphQL dependency is optional in versioned,
// and the following implementation relies on existence of this class (in GraphQL v4)
if (!interface_exists(OperationCreator::class)) {
    return;
}

/**
 * Scaffolds a "copy to stage" operation for DataObjects.
 *
 * copy[TypeName]ToStage(ID!, FromVersion!, FromStage!, ToStage!)
 *
 */
class CopyToStageCreator implements OperationCreator
{
    use Configurable;
    use Injectable;

    /**
     * @var array
     * @config
     */
    private static $default_plugins = [];

    /**
     * @param SchemaModelInterface $model
     * @param string $typeName
     * @param array $config
     * @return ModelOperation|null
     * @throws SchemaBuilderException
     */
    public function createOperation(
        SchemaModelInterface $model,
        string $typeName,
        array $config = []
    ): ?ModelOperation {
        if (!ViewableData::has_extension($model->getSourceClass(), Versioned::class)) {
            return null;
        }

        $plugins = $config['plugins'] ?? [];
        $mutationName = $config['name'] ?? null;
        if (!$mutationName) {
            $mutationName = 'copy' . ucfirst($typeName ?? '') . 'ToStage';
        }

        return ModelMutation::create($model, $mutationName)
            ->setType($typeName)
            ->setPlugins($plugins)
            ->setResolver([VersionedResolver::class, 'resolveCopyToStage'])
            ->addResolverContext('dataClass', $model->getSourceClass())
            ->addArg('input', 'CopyToStageInputType!');
    }
}
