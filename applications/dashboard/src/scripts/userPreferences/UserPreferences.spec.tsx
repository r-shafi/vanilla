/**
 * @author Jenny Seburn <jseburn@higherlogic.com>
 * @copyright 2009-2023 Vanilla Forums Inc.
 * @license Proprietary
 */

import { UserPreferences } from "@dashboard/userPreferences/UserPreferences";
import { LoadStatus } from "@library/@types/api/core";
import { TestReduxProvider } from "@library/__tests__/TestReduxProvider";
import { mockAPI } from "@library/__tests__/utility";
import { NotificationPreferencesContextProvider } from "@library/notificationPreferences";
import { createMockApi } from "@library/notificationPreferences/fixtures/NotificationPreferences.fixtures";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { stableObjectHash } from "@vanilla/utils";
import React from "react";

function MockWrappedUserPreferences() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
        },
    });
    const mockApi = createMockApi();
    return (
        <QueryClientProvider client={queryClient}>
            <TestReduxProvider
                state={{
                    config: {
                        configsByLookupKey: {
                            [stableObjectHash(["preferences.categoryFollowed.defaults"])]: {
                                status: LoadStatus.SUCCESS,
                                data: {
                                    "preferences.categoryFollowed.defaults": "[]",
                                },
                            },
                        },
                    },
                }}
            >
                <NotificationPreferencesContextProvider {...{ api: mockApi, userID: "defaults" }}>
                    <UserPreferences />
                </NotificationPreferencesContextProvider>
            </TestReduxProvider>
        </QueryClientProvider>
    );
}

function MockComponent() {
    return <span data-testid={"mockComponent"}>This is a simple component rendering some text</span>;
}

UserPreferences.registerExtraPreference({
    key: "mockPreference",
    component: <MockComponent />,
});

const mockAdapter = mockAPI();

describe("UserPreferences Page", () => {
    beforeEach(() => {
        mockAdapter.onGet(/(categories|notification-preferences).+$/).reply(200, []);
    });
    it("Includes option to set default notification preferences", () => {
        render(<MockWrappedUserPreferences />);

        expect(screen.getByText(/Default Notification Preferences/)).toBeInTheDocument();
        expect(screen.getByRole("button", { name: "Edit Default Notifications" })).toBeInTheDocument();
    });

    it("Includes option to set default followed categories", () => {
        render(<MockWrappedUserPreferences />);

        expect(screen.getByText(/Default Followed Categories/)).toBeInTheDocument();
        expect(screen.getByRole("button", { name: "Edit Default Categories" })).toBeInTheDocument();
    });

    it("Renders other registered components", () => {
        render(<MockWrappedUserPreferences />);
        expect(screen.getByTestId("mockComponent")).toBeInTheDocument();
    });
});
