<?php

namespace SilverStripe\Versioned\Task;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\File;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Logging\PreformattedEchoHandler;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\Versioned\Versioned;

/**
 * Migrates files that have been changed from unversioned to versioned by application of the Versioned extension.
 *
 * Any existing DataObject created before the Versioned extension is applied was previously "always live", but now is
 * in the "draft" state, as Versioned uses the 'base table' for draft content. This has the side effect of removing all
 * previously unversioned data on the website from public view.
 *
 * This task fixes this by publishing records with no version information.
 *
 * NOTE: This task relies on classes listed within the database to still exist. This means any
 * `SilverStripe\ORM\DatabaseAdmin.classname_value_remapping` definitions should always be run _first_. This happens as
 * part of a normal `dev/build` process, but is important to mention here as valuable debugging information. Ensure
 * that all modules & classes have a valid e.g. `_config/legacy.yml` defined for all DataObjects.
 */
class PublishNewlyVersionedDataObjects extends BuildTask
{
    /**
     * Allows classes to be ingored by this automated procedure
     * e.g. in the case that there is a more complex migration task performed elsewhere.
     *
     * @config
     * @var array
     */
    private static $ignore_classes = [
        File::class, // Handled by the File Migration task in `silverstripe/assets`
    ];

    /**
     * Apply extra filters to select what will be published from the unversioned records
     * Map of:
     *   PHP class name (fully qualified with namespace) => WHERE clasue (escaped SQL string)
     *
     * @config
     * @var array
     */
    private static $extra_filters = [];

    /**
     * Prefix all logs output with this, for easier location in a log stream (e.g. syslog)
     *
     * @config
     * @var string
     */
    private static $log_prefix = __CLASS__ . ': ';

    private static $segment = 'PublishPreviouslyUnversionedItems';

    private static $dependencies = [
        'Logger' => '%$' . LoggerInterface::class . '.MigrationTask.PublishLiveObjects',
    ];

    /**
     * @var LoggerInterface
     */
    private $logger;

    protected $title = 'Publish existing DataObjects that have recently had the Versioned extension applied to them';

    protected $description = 'Ensure that previously unversioned (always "live") items are still accessible to a'
        . ' visitor by publishing them all, as the "main" table is now the "draft" table.';

    /**
     * Find and publish draft DataObjects with no version.
     *
     * This is detected by leveraging that creating a Versioned object always writes a version, thus all objects that
     * exist in draft but have no versions at all are those that need updating to continue to be live (as all
     * unversioned objects are "always live"). Because of this, this script should be safe to run more than once.
     *
     * Care is taken to not cascade the publish, as this could lead to issues with any new owns relationship that may
     * have been set up along with the addition of the Versioned extension. The related items will probably be
     * published singularly in time with the run of this task.
     *
     * @param HTTPRequest $request
     * @return void
     */
    public function run($request)
    {
        $this->configureLogger();
        $loggerMessagePrefix = $this->config()->get('log_prefix');
        $this->logger->info($loggerMessagePrefix . 'Beginning');

        $ignoredClasses = $this->config()->get('ignore_classes');
        $versionedClasses = array_filter($this->findVersionedClasses(), function ($class) use ($ignoredClasses) {
            return !in_array($class, $ignoredClasses);
        });
        $extraFilters = $this->config()->get('extra_filters');

        try {
            foreach ($versionedClasses as $class) {
                $table = Injector::inst()->get(DataObjectSchema::class)->baseDataTable($class);
                $versionTable = "${table}_Versions";
                $unversioned = Versioned::get_by_stage($class, Versioned::DRAFT)
                    ->leftJoin($versionTable, "\"$versionTable\".\"RecordID\" = \"$table\".\"ID\"")
                    ->where("\"$versionTable\".\"ID\" IS NULL");

                if (isset($extraFilters[$class])) {
                    $unversioned = $unversioned->where($extraFilters[$class]);
                }

                $count = $unversioned->count();
                if ($count) {
                    $this->logger->info($loggerMessagePrefix . "$class - $count unversioned records");
                }
                $unversioned->each(function ($instance) use ($class, $loggerMessagePrefix) {
                    /** @var DataObject $instance */
                    $instance->publishSingle();
                    $this->logger->info($loggerMessagePrefix . "- Published $class # $instance->ID");
                });
            }

            $this->logger->info($loggerMessagePrefix . 'Complete');
        } catch (Exception $exception) {
            $this->logger->error($loggerMessagePrefix . 'Failed - ' . $exception->getMessage());
            throw $exception;
        }
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Adds configuration to the logger fetched by dependency injection {@see Injector}
     *
     * This allows any existing hanlders to co-exist, where as a service definition for this task only would override
     * any handlers that were already configured (e.g. SysLog handler) on a per-project basis.
     *
     * @return void
     */
    private function configureLogger()
    {
        if (Director::is_cli()) {
            $this->logger->pushHandler(new StreamHandler('php://stdout'));
            $this->logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));
        } else {
            $this->logger->pushHandler(new PreformattedEchoHandler());
        }
    }

    /**
     * Locate all DataObjects that are Versioned, but not their subclasses (which will also be Versioned)
     *
     * @return string[]
     */
    private function findVersionedClasses()
    {
        return array_filter(
            ClassInfo::subclassesFor(DataObject::class),
            function ($class) {
                return (
                    DataObject::has_extension($class, Versioned::class)
                    && in_array(
                        Versioned::class,
                        (array) Config::forClass($class)->uninherited('extensions')
                    )
                    && DataObject::singleton($class)->hasStages()
                );
            }
        );
    }
}
