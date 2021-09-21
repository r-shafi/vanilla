/**
 * @author Dominic Lacaille <dominic.lacaille@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license gpl-2.0-only
 */

import { DashboardCodeEditor } from "@dashboard/forms/DashboardCodeEditor";
import { DashboardFormGroup } from "@dashboard/forms/DashboardFormGroup";
import { DashboardInput } from "@dashboard/forms/DashboardInput";
import { DashboardRadioButton } from "@dashboard/forms/DashboardRadioButton";
import { DashboardRadioGroup } from "@dashboard/forms/DashboardRadioGroups";
import { DashboardToggle } from "@dashboard/forms/DashboardToggle";
import apiv2 from "@library/apiv2";
import { FormTreeControl } from "@library/tree/FormTreeControl";
import { useUniqueID } from "@library/utility/idUtils";
import { IControlGroupProps, IControlProps } from "@vanilla/json-schema-forms";
import { AutoComplete, AutoCompleteOption, IFormGroupProps } from "@vanilla/ui";
import { AutoCompleteLookupOptions } from "@vanilla/ui/src/forms/autoComplete/AutoCompleteLookupOptions";
import React, { useEffect } from "react";

/**
 * This is intended for use in the JsonSchemaForm component
 * TODO: We need to replace these inputs with vanilla-ui
 * Important: An exception will occur if this is used without DashboardFormControlGroup
 * @param props
 * @returns
 */
export function DashboardFormControl(props: IControlProps) {
    const { control, required, disabled, instance, schema, onChange } = props;
    const value = instance ?? schema.default;
    const inputName = useUniqueID("input");

    switch (control.inputType) {
        case "textBox":
            const isMultiline = control.type === "textarea";
            const typeIsNumber = control.type === "number";
            const typeIsUrl = control.type === "url";
            const type = typeIsNumber ? "number" : typeIsUrl ? "url" : "text";
            return (
                <DashboardInput
                    inputProps={{
                        value: value ?? "",
                        disabled,
                        onChange: (event) => onChange(event.target.value),
                        maxLength: schema.type === "string" ? schema.maxLength : undefined,
                        type: !isMultiline ? type : undefined,
                        placeholder: control.placeholder,
                        multiline: isMultiline ? true : false,
                    }}
                    multiLineProps={
                        isMultiline
                            ? {
                                  rows: 4,
                              }
                            : undefined
                    }
                />
            );
        case "codeBox":
            return (
                <DashboardCodeEditor
                    value={value}
                    onChange={onChange}
                    language={control.language || "text/html"}
                    jsonSchemaUri={control.jsonSchemaUri}
                />
            );
        case "radio":
            return (
                <DashboardRadioGroup value={value} onChange={onChange}>
                    {Object.entries(control.choices.staticOptions ?? []).map(
                        ([optionValue, label]: [string, string]) => (
                            <DashboardRadioButton
                                disabled={props.disabled}
                                name={inputName}
                                key={optionValue}
                                label={label}
                                value={optionValue}
                            />
                        ),
                    )}
                </DashboardRadioGroup>
            );
        case "dropDown":
            const { api, staticOptions } = control.choices;
            return (
                <div className="input-wrap">
                    <AutoComplete
                        value={value}
                        clear={!required}
                        placeholder={control.placeholder}
                        onChange={(value) => {
                            onChange(value);
                        }}
                    >
                        {api && <AutoCompleteLookupOptions api={apiv2} lookup={api} />}
                        {staticOptions &&
                            Object.entries(staticOptions).map(([value, label]) => (
                                <AutoCompleteOption key={value} value={value} label={String(label)} />
                            ))}
                    </AutoComplete>
                </div>
            );
        case "checkBox":
        case "toggle":
            return <DashboardToggle disabled={props.disabled} checked={value} onChange={onChange} />;
        case "dragAndDrop":
            return <FormTreeControl {...(props as any)} />;
        default:
            return <div>{(control as any).inputType} is not supported</div>;
    }
}

/**
 * This is intended for use in the JsonSchemaForm component
 * @param props
 * @returns
 */
export function DashboardFormControlGroup(props: React.PropsWithChildren<IControlGroupProps> & IFormGroupProps) {
    const { children, controls } = props;
    const { sideBySide, inputID } = props;
    const { label, description, fullSize } = controls[0];
    if (fullSize) {
        return <>{children}</>;
    }

    return (
        <DashboardFormGroup label={label ?? ""} description={description}>
            {children}
        </DashboardFormGroup>
    );
}
