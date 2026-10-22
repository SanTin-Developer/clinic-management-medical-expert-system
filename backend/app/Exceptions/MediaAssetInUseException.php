<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a media asset is still referenced by one or more CMS records.
 * The references are safe to return to an authenticated administrator.
 */
class MediaAssetInUseException extends RuntimeException
{
    /**
     * @param  list<array{type: string, id: int, label: string}>  $references
     */
    public function __construct(public readonly array $references)
    {
        $labels = array_map(
            static fn (array $reference): string => $reference['label'],
            array_slice($references, 0, 4),
        );

        $summary = implode(', ', $labels);

        if (count($references) > count($labels)) {
            $summary .= sprintf(' and %d more', count($references) - count($labels));
        }

        parent::__construct(
            'This image is still used by '.count($references)
            .' record(s): '.$summary.'. Remove those references before permanently deleting it.'
        );
    }
}
