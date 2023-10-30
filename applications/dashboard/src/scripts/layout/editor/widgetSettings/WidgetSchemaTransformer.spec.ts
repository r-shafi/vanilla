/**
 * @copyright 2009-2023 Vanilla Forums Inc.
 * @license gpl-2.0-only
 */

import { JsonSchema } from "@vanilla/json-schema-forms";
import { widgetsSchemaTransformer } from "@dashboard/layout/editor/widgetSettings/WidgetSchemaTransformer";
import { WidgetContainerDisplayType } from "@library/homeWidget/HomeWidgetContainer.styles";
import { setMeta } from "@library/utility/appUtils";

describe("WidgetSchemaTransformer", () => {
    const mockSchema: JsonSchema = {
        type: "object",
        description: "Random schema",
        properties: {
            apiParams: {
                type: "object",
                properties: {
                    limit: {
                        type: "number",
                        "x-control": {
                            type: "number",
                            inputType: "textBox",
                        },
                    },
                },
            },
        },
        required: [],
    };
    const mockInitialValue: any = {
        apiParams: {},
        someValue: 10,
    };
    const mockCategoriesSchema: JsonSchema = {
        type: "object",
        description: "Categories",
        properties: {
            apiParams: {
                type: "object",
                properties: {},
            },
            itemOptions: {
                properties: {
                    contentType: {
                        enum: [
                            "title-background",
                            "title-description",
                            "title-description-icon",
                            "title-description-image",
                        ],
                        "x-control": {
                            choices: {
                                staticOptions: {
                                    "title-background": "Background",
                                    "title-description": "None",
                                    "title-description-icon": "Icon",
                                    "title-description-image": "Image",
                                },
                            },
                        },
                    },
                    fallbackIcon: {},
                },
            },
            categoryOptions: {
                properties: {
                    followButton: {
                        properties: {
                            display: {
                                "x-control": "someControl",
                            },
                        },
                    },
                    metas: {
                        properties: {
                            asIcons: {
                                "x-control": "someControl",
                            },
                            display: {
                                properties: {
                                    postCount: {
                                        "x-control": "someControl",
                                    },
                                    lastPostAuthor: {
                                        "x-control": "someControl",
                                    },
                                },
                            },
                        },
                    },
                },
            },
        },
        required: [],
    };

    it("Transformer function returns the same schema/value if no tranformation made.", () => {
        const { transformedSchema, value } = widgetsSchemaTransformer(mockSchema, {}, mockInitialValue);

        // same value received
        expect(value).toEqual(mockInitialValue);
        expect(value.someValue).toBe(mockInitialValue.someValue);
        expect(value.apiParams).toBeTruthy();

        // same initial schema, plus middleware
        expect(transformedSchema.description).toBe(mockSchema.description);
        expect(transformedSchema.properties.apiParams).toMatchObject(mockSchema.properties.apiParams);
        expect(transformedSchema.properties.$middleware).toBeTruthy();
    });

    it("Categories widget transformations.", () => {
        let mockCategoriesInitialValue: any = {
            apiParams: {},
        };
        const { transformedSchema } = widgetsSchemaTransformer(mockCategoriesSchema, {}, mockCategoriesInitialValue);

        const initialContentType = mockCategoriesSchema.properties.itemOptions.properties.contentType;
        let receivedContentType = transformedSchema.properties.itemOptions.properties.contentType;

        // if no containerOptions displayType as initial value, or displayType is "list", itemOptions contentType should not have "Background" option
        expect(receivedContentType.enum.find((option) => option === "title-background")).not.toBeTruthy();
        expect(receivedContentType["x-control"].choices.staticOptions["title-background"]).toBeUndefined();
        receivedContentType.enum.forEach((option) => {
            expect(initialContentType.enum.includes(option)).toBeTruthy();
            expect(receivedContentType["x-control"].choices.staticOptions[option]).toBeDefined();
        });

        // containerOptions  displayType is "grid"/"carousel", itemOptions contentType should contain all options
        mockCategoriesInitialValue["containerOptions"] = { displayType: WidgetContainerDisplayType.GRID };

        const { transformedSchema: transformedSchema1 } = widgetsSchemaTransformer(
            mockCategoriesSchema,
            {},
            mockCategoriesInitialValue,
        );
        receivedContentType = transformedSchema1.properties.itemOptions.properties.contentType;

        expect(receivedContentType.enum.length).toBe(initialContentType.enum.length);
        receivedContentType.enum.forEach((option) => {
            expect(receivedContentType["x-control"].choices.staticOptions[option]).toBeDefined();
        });

        // containerOptions  displayType is "link", no itemOptions contentType options, also, if we had initial contentType option value, we should clean that out
        mockCategoriesInitialValue["containerOptions"] = { displayType: WidgetContainerDisplayType.LINK };
        mockCategoriesInitialValue["itemOptions"] = { contentType: "title-background" };

        const { transformedSchema: transformedSchema2, value } = widgetsSchemaTransformer(
            mockCategoriesSchema,
            {},
            mockCategoriesInitialValue,
        );
        receivedContentType = transformedSchema2.properties.itemOptions.properties.contentType;

        expect(value.itemOptions.contentType).toBe("title-description");
        expect(receivedContentType["x-control"].choices).toBeUndefined();
        expect(receivedContentType["x-control"].inputType).toBe("custom");
    });

    it("Meta transformations (categories widget).", () => {
        setMeta("featureFlags.layoutEditor.categoryList.Enabled", true);
        const mockInitialValue: any = {
            apiParams: {},
            containerOptions: {
                displayType: WidgetContainerDisplayType.GRID,
            },
        };
        const { transformedSchema } = widgetsSchemaTransformer(mockCategoriesSchema, {}, mockInitialValue);

        // for grid/carousel, no follow button and limited meta
        const receivedCategoryOptions = transformedSchema.properties.categoryOptions.properties;
        expect(receivedCategoryOptions.followButton.properties.display["x-control"]).toBeUndefined();
        expect(receivedCategoryOptions.followButton.properties.display.default).toBe(false);
        expect(receivedCategoryOptions.metas.properties.display.properties.postCount["x-control"]).toBeDefined();
        expect(receivedCategoryOptions.metas.properties.display.properties.lastPostAuthor["x-control"]).toBeUndefined();

        // no meta options at all for link type
        mockInitialValue.containerOptions.displayType = WidgetContainerDisplayType.LINK;

        const { transformedSchema: transformedSchema2 } = widgetsSchemaTransformer(
            mockCategoriesSchema,
            {},
            mockInitialValue,
        );

        const receivedCategoryOptions2 = transformedSchema2.properties.categoryOptions.properties;
        expect(receivedCategoryOptions2.metas["x-control"].inputType).toBe("custom");
    });
});
