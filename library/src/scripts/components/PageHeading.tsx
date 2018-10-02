/*
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import * as React from "react";
import classNames from "classnames";
import BackLink from "BackLink";
import Heading from "Heading";

interface IPageHeading {
    title: string;
    backUrl?: string; // back link
    className?: string;
    menu?: JSX.Element;
}

export default class PageHeading extends React.Component<IPageHeading> {
    public render() {
        return (
            <div className={classNames("pageHeading", this.props.className)}>
                <div className="pageHeading-main">
                    <BackLink url={this.props.backUrl} className="pageHeading-backLink" />
                    {/* Will not render if no url is passed */}
                    <Heading title={this.props.title} depth={1} />
                </div>
                {this.props.menu && <div className="pageHeading-actions">{this.props.menu}</div>}
            </div>
        );
    }
}
