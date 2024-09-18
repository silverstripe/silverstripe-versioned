<?php

namespace SilverStripe\Versioned\Tests;

use InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\ReadingMode;
use PHPUnit\Framework\Attributes\DataProvider;

class ReadingModeTest extends SapphireTest
{
    /**
     * @param string $readingMode
     * @param array $dataQuery
     * @param array $queryStringArray
     * @param string $queryString
     */
    #[DataProvider('provideReadingModes')]
    public function testToDataQueryParams($readingMode, $dataQuery, $queryStringArray, $queryString)
    {
        $this->assertEquals(
            $dataQuery,
            ReadingMode::toDataQueryParams($readingMode),
            "Convert {$readingMode} to dataquery parameters"
        );
    }
    /**
     * @param string $readingMode
     * @param array $dataQuery
     * @param array $queryStringArray
     * @param string $queryString
     */
    #[DataProvider('provideReadingModes')]
    public function testFromDataQueryParameters($readingMode, $dataQuery, $queryStringArray, $queryString)
    {
        $this->assertEquals(
            $readingMode,
            ReadingMode::fromDataQueryParams($dataQuery),
            "Convert {$readingMode} from dataquery parameters"
        );
    }

    /**
     * @param string $readingMode
     * @param array $dataQuery
     * @param array $queryStringArray
     * @param string $queryString
     */
    #[DataProvider('provideReadingModes')]
    public function testToQueryString($readingMode, $dataQuery, $queryStringArray, $queryString)
    {
        $this->assertEquals(
            $queryStringArray,
            ReadingMode::toQueryString($readingMode),
            "Convert {$readingMode} to querystring array"
        );
    }

    /**
     * @param string $readingMode
     * @param array $dataQuery
     * @param array $queryStringArray
     * @param string $queryString
     */
    #[DataProvider('provideReadingModes')]
    public function testFromQueryString($readingMode, $dataQuery, $queryStringArray, $queryString)
    {
        $this->assertEquals(
            $readingMode,
            ReadingMode::fromQueryString($queryStringArray),
            "Convert {$readingMode} from querystring array"
        );
        $this->assertEquals(
            $readingMode,
            ReadingMode::fromQueryString($queryString),
            "Convert {$readingMode} from querystring encoded string"
        );
    }

    /**
     * Return list of reading modes in order:
     *  - reading mode string
     *  - dataquery params array
     *  - query string array
     *  - query string (string)
     * @return array
     */
    public static function provideReadingModes()
    {
        return [
            // Draft
            [
                'Stage.Stage',
                [
                    'Versioned.mode' => 'stage',
                    'Versioned.stage' => 'Stage',
                ],
                [
                    'stage' => 'Stage',
                ],
                'stage=Stage'
            ],
            // Live
            [
                'Stage.Live',
                [
                    'Versioned.mode' => 'stage',
                    'Versioned.stage' => 'Live',
                ],
                [
                    'stage' => 'Live',
                ],
                'stage=Live'
            ],
            // Draft archive
            [
                'Archive.2017-11-15 11:31:42.Stage',
                [
                    'Versioned.mode' => 'archive',
                    'Versioned.date' => '2017-11-15 11:31:42',
                    'Versioned.stage' => 'Stage',
                ],
                [
                    'archiveDate' => '2017-11-15 11:31:42',
                    'stage' => 'Stage',
                ],
                'archiveDate=2017-11-15+11%3A31%3A42&stage=Stage',
            ],
            // Live archive
            [
                'Archive.2017-11-15 11:31:42.Live',
                [
                    'Versioned.mode' => 'archive',
                    'Versioned.date' => '2017-11-15 11:31:42',
                    'Versioned.stage' => 'Live',
                ],
                [
                    'archiveDate' => '2017-11-15 11:31:42',
                    'stage' => 'Live',
                ],
                'archiveDate=2017-11-15+11%3A31%3A42&stage=Live',
            ],
        ];
    }

    /**
     * @param string $stage
     */
    #[DataProvider('provideTestInvalidStage')]
    public function testInvalidStage($stage)
    {
        $this->expectException(\InvalidArgumentException::class);
        ReadingMode::validateStage($stage);
    }

    public static function provideTestInvalidStage()
    {
        return [
            [''],
            ['stage'],
            ['bob'],
        ];
    }
}
