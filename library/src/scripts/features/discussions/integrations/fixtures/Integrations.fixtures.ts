/**
 * @author Mihran Abrahamian <mihran.abrahamian@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license gpl-2.0-only
 */

import {
    IAttachment,
    IAttachmentIntegration,
    IAttachmentIntegrationCatalog,
    IIntegrationsApi,
} from "@library/features/discussions/integrations/Integrations.types";
import { JsonSchema } from "@vanilla/json-schema-forms";

export const FAKE_INTEGRATION: IAttachmentIntegration = {
    label: "Fake Integration for Discussion",
    attachmentType: "fakeIntegration",
    recordTypes: ["discussion", "comment"],
    submitButton: "Create Task",
    externalIDLabel: "Task #",
    title: "Todo List Service - Task",
    logoIcon: "meta-external",
};

export const FAKE_INTEGRATIONS_CATALOG: IAttachmentIntegrationCatalog = {
    [FAKE_INTEGRATION.attachmentType]: FAKE_INTEGRATION,
};

export const FAKE_INTEGRATION_SCHEMAS: Record<string, JsonSchema> = {
    [FAKE_INTEGRATION.attachmentType]: {
        type: "object",
        properties: {
            title: {
                type: "string",
                title: "Title",
                default: "A new task",
                "x-control": {
                    label: "Title",
                    inputType: "textBox",
                },
            },
            description: {
                type: "string",
                title: "Description",
                default: "A fake description",
                "x-control": {
                    label: "Description",
                    inputType: "textBox",
                    type: "textarea",
                },
            },
        },
        required: ["title"],
    },
};

export const FAKE_ATTACHMENT: IAttachment = {
    attachmentID: 1,
    attachmentType: "fakeIntegration",
    recordType: "discussion",
    recordID: `${9999999}`,
    state: "Completed",
    sourceID: `${123456}`,
    sourceUrl: "#",
    dateInserted: "2020-10-06T15:30:44+00:00",
    metadata: [
        {
            labelCode: "Name",
            value: "This content is generated by users on the site. You can't update it here.",
        },
        {
            labelCode: "Title",
            value: "This content is generated by users on the site. You can't update it here.",
        },
        {
            labelCode: "Company",
            value: "This content is generated by users on the site. You can't update it here.",
        },
        {
            labelCode: "Favourite Color",
            value: "This content is generated by users on the site. You can't update it here.",
        },
        {
            labelCode: "More Information",
            value: "This content is generated by users on the site. You can't update it here.",
        },
        {
            labelCode: "Subcontractor",
            value: "This content is generated by users on the site. You can't update it here.",
        },
    ],
};

export const FAKE_API: IIntegrationsApi = {
    getIntegrationsCatalog: async () => FAKE_INTEGRATIONS_CATALOG,
    getAttachmentSchema: async (params) => FAKE_INTEGRATION_SCHEMAS[params.attachmentType],
    postAttachment: async (params) => ({} as any),
};