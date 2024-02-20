<?php
/**
 * @author Richard Flynn <rflynn@higherlogic.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\Dashboard\Models;

use Garden\Schema\Schema;

/**
 * Interface for external issue providers.
 */
interface ExternalIssueProviderInterface
{
    // const TYPE_NAME {should match the name of the issue type};

    /**
     * Create a new issue in the external service and return the saved associated attachment data.
     *
     * @param string $recordType
     * @param int $recordID
     * @param array $issueData
     * @return array
     */
    public function makeNewIssue(string $recordType, int $recordID, array $issueData): array;

    /**
     * The schema for required special posting fields.
     *
     * @return Schema
     */
    public function issuePostSchema(): \Garden\Schema\Schema;

    /**
     * The schema for the full issue data.
     *
     * @return Schema
     */
    public function fullIssueSchema(): \Garden\Schema\Schema;

    /**
     * Get the type name of the provider.
     *
     * @return string
     */
    public function getTypeName(): string;

    /**
     * Get the form schema for creating the external issue with fields dynamically populated from the record.
     *
     * @param string $recordType
     * @param int $recordID
     * @return Schema
     */
    public function getHydratedFormSchema(string $recordType, int $recordID): Schema;
    /**
     * Verify that the user is authorized to use this provider.
     *
     * @param $user
     * @return bool
     */
    public function validatePermissions($user): bool;

    /**
     * Get the types of records that can be used with this provider.
     *
     * @return array
     */
    public function getRecordTypes(): array;

    /**
     * Return the catalog of the provider to be process by the front-end.
     *
     * @return array
     */
    public function getCatalog(): array;

    //    abstract public function getOptions(string $recordType, array $record): array;
}
