/**
 * @copyright 2009-2023 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import React, { useMemo } from "react";
import { ICategoriesWidgetProps } from "@library/categoriesWidget/CategoriesWidget";
import { t } from "@vanilla/i18n";
import { categoryListClasses } from "@library/categoriesWidget/CategoryList.classes";
import {
    IHomeWidgetContainerOptions,
    WidgetContainerDisplayType,
    homeWidgetContainerClasses,
} from "@library/homeWidget/HomeWidgetContainer.styles";
import { HomeWidgetContainer, HomeWidgetGridContainer } from "@library/homeWidget/HomeWidgetContainer";
import { groupCategoryItems } from "@library/categoriesWidget/CategoriesWidget.utils";
import CategoryItem, { ICategoryItem } from "@library/categoriesWidget/CategoryItem";
import Heading from "@library/layout/Heading";
import { ICategoryItemOptions } from "@library/categoriesWidget/CategoryList.variables";
import { homeWidgetItemClasses } from "@library/homeWidget/HomeWidgetItem.styles";
import cloneDeep from "lodash/cloneDeep";
import { cx } from "@emotion/css";

export function CategoryGrid(props: ICategoriesWidgetProps) {
    const { containerOptions, categoryOptions } = props;

    const isGrid = props.containerOptions?.displayType === WidgetContainerDisplayType.GRID;

    const categoryDefaultOptions: ICategoryItemOptions = {
        ...categoryOptions,
        contentType: props.itemOptions?.contentType,
        imagePlacement: "top",
        //metas for grid/carousel are predefined and always rendered as icons
        metas: {
            ...categoryOptions?.metas,
            asIcons: true,
            display: {
                ...categoryOptions?.metas?.display,
                postCount: categoryOptions?.metas?.display?.postCount,
                discussionCount: categoryOptions?.metas?.display?.discussionCount,
                commentCount: categoryOptions?.metas?.display?.commentCount,
                followerCount: categoryOptions?.metas?.display?.followerCount,
                lastPostName: false,
                lastPostAuthor: false,
                lastPostDate: false,
                subcategories: false,
            },
        },
    };

    const classes = categoryListClasses(categoryDefaultOptions, true);

    const itemData = cloneDeep(props.itemData);

    // for grid view with headings
    const categoryGroups = useMemo(() => {
        return groupCategoryItems(itemData);
    }, [itemData]);

    // for carousel, no headings
    const discussionCategories = itemData.filter((item) => item.displayAs !== "heading");

    return (
        <HomeWidgetContainer
            title={props.title}
            subtitle={props.subtitle}
            description={props.description}
            options={{
                ...props.containerOptions,
            }}
        >
            {isGrid ? (
                <div className={classes.gridContainer}>
                    {itemData.length === 0 ? (
                        <div>{t("No categories were found.")}</div>
                    ) : (
                        categoryGroups.map((categoryGroup, i) => {
                            return (
                                <CategoryGridItemsGroup
                                    options={categoryDefaultOptions}
                                    group={categoryGroup as ICategoryGroup["group"]}
                                    key={i}
                                    containerOptions={containerOptions}
                                />
                            );
                        })
                    )}
                </div>
            ) : (
                discussionCategories.map((item, i) => {
                    return (
                        <CategoryItem
                            key={i}
                            category={item}
                            asTile
                            options={categoryDefaultOptions}
                            className={homeWidgetItemClasses().root}
                        />
                    );
                })
            )}
        </HomeWidgetContainer>
    );
}

interface ICategoryGroup {
    options?: ICategoryItemOptions;
    group: Omit<ICategoryItem, "categoryID" | "to" | "counts">;
    isFirstItem?: boolean;
    containerOptions?: IHomeWidgetContainerOptions;
}

function CategoryGridItemsGroup(props: ICategoryGroup) {
    const { group, options, containerOptions } = props;
    const classes = categoryListClasses(options, true);
    const isHeading = group.displayAs === "heading";
    const containerClasses = homeWidgetContainerClasses(props.containerOptions);

    if (isHeading && group.depth <= 2) {
        return (
            <>
                <div className={classes.gridHeadingWrapper}>
                    <Heading
                        depth={group.depth + 1}
                        className={cx(
                            { [classes.firstLevelHeading]: group.depth === 1 },
                            { [classes.secondLevelHeading]: group.depth === 2 },
                        )}
                    >
                        {group.name}
                    </Heading>
                    {!group.children?.length && <div className={classes.message}>{group.noChildCategoriesMessage}</div>}
                </div>
                {group.children?.map((child, i) => {
                    return (
                        <CategoryGridItemsGroup
                            key={i}
                            group={child}
                            options={options}
                            containerOptions={containerOptions}
                        />
                    );
                })}
            </>
        );
    }
    if (group.displayAs === "gridItemsGroup") {
        //when grid items don't fill all the container space we need to fill that space with extra spacers so the flexBox do't break, just like we do in HomeWidget
        let extraSpacerItemCount = 0;
        if (group.children && group.children.length < (containerOptions?.maxColumnCount ?? 3)) {
            extraSpacerItemCount = (containerOptions?.maxColumnCount ?? 3) - group.children.length;
        }
        return (
            <div className={classes.gridGroup}>
                <HomeWidgetGridContainer
                    options={{
                        displayType: WidgetContainerDisplayType.GRID,
                        maxColumnCount: containerOptions?.maxColumnCount,
                    }}
                >
                    {group.children?.map((child, i) => {
                        return (
                            <CategoryItem
                                key={i}
                                category={child}
                                asTile
                                options={options}
                                className={homeWidgetItemClasses().root}
                            />
                        );
                    })}
                    {[...new Array(extraSpacerItemCount)].map((_, i) => {
                        return <div key={"spacer-" + i} className={containerClasses.gridItemSpacer}></div>;
                    })}
                </HomeWidgetGridContainer>
            </div>
        );
    }
    return <></>;
}

export default CategoryGrid;
