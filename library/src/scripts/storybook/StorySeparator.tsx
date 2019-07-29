/**
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import React from "react";
import { storyBookClasses } from "@library/storybook/StoryBookStyles";

interface IProps {}

/**
 * Separator, for react storybook.
 */
export function StorySeparator(props: IProps) {
    const classes = storyBookClasses();
    return <hr className={classes.separator} />;
}
