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


if (!class_exists(OperationCreator::class)) {
    return;
}

/**
 * Scaffolds a "copy to stage" operation for DataObjects.
 *
 * copy[TypeName]ToStage(ID!, FromVersion!, FromStage!, ToStage!)
 *
 * @internal This is a low level API that might be removed in the future. Consider using the "rollback" mutation instead
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
     * @return ModelOperation
     * @throws SchemaBuilderException
     */
    public function createOperation(
        SchemaModelInterface $model,
        string $typeName,
        array $config = []
    ): ModelOperation {
        if (!Extensible::has_extension($model->getSourceClass(), Versioned::class)) {
            return null;
        }

        $defaultPlugins = $this->config()->get('default_plugins');
        $configPlugins = $config['plugins'] ?? [];
        $plugins = array_merge($defaultPlugins, $configPlugins);
        $mutationName = 'copy' . ucfirst($typeName) . 'ToStage';

        return ModelMutation::create($model, $mutationName)
            ->setType($typeName)
            ->setPlugins($plugins)
            ->setDefaultResolver([VersionedResolver::class, 'resolveCopyToStage'])
            ->addResolverContext('dataClass', $model->getSourceClass())
            ->addArg('input', 'CopyToStageInputType!');
    }
}
