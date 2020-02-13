<?php

namespace SilverStripe\Versioned\State;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Versioned\ReadingMode as ReadingModeValidator;
use SilverStripe\Versioned\Versioned;

class ReadingMode
{

    use Injectable;

    /**
     * The default reading mode is live
     */
    const DEFAULT = 'Stage.' . Versioned::LIVE;

    /**
     * Current reading mode. Supports stage / archive modes.
     *
     * @var string
     */
    protected $readingMode = null;

    /**
     * Default reading mode, if none set.
     * Any modes which differ to this value should be assigned via querystring / session (if enabled)
     *
     * @var string|null
     */
    protected $defaultReadingMode = ReadingMode::DEFAULT;

    /**
     * @param string|null $readingMode
     * @param bool $prependStage - Prepend 'Stage.', set to true by default
     */
    public function set(?string $readingMode, bool $prependStage = true): void
    {
        $this->readingMode = $prependStage && $readingMode !== null && $readingMode !== ''
            ? 'Stage.' . $readingMode
            : $readingMode;
    }

    public function setWithArchiveDate(string $date, string $stage = Versioned::DRAFT): void
    {
        ReadingModeValidator::validateStage($stage);
        $this->set('Archive.' . $date . '.' . $stage, false);
    }

    /**
     * @return string|null
     */
    public function get(): ?string
    {
        return $this->readingMode;
    }

    /**
     * @param string|null $readingMode
     */
    public function setDefault(?string $readingMode): void
    {
        $this->defaultReadingMode = $readingMode;
    }

    /**
     * @return string|null
     */
    public function getDefault(): ?string
    {
        return $this->defaultReadingMode ?: static::DEFAULT;
    }

    /**
     * Reset the reading mode
     */
    public function reset(): void
    {
        $this->set('');
        Controller::curr()->getRequest()->getSession()->clear('readingMode');
    }
}
