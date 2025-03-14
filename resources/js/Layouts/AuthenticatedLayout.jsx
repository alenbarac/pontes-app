import React, { useState } from "react";
import { usePage } from "@inertiajs/react";
import Sidebar from "@/Components/Sidebar"; // Ensure correct import path
import Header from "@/Components/Header"; // Ensure correct import path

export default function Authenticated({ header, children }) {
    const user = usePage()?.props?.auth?.user; // Ensure safe access

    // State for managing the sidebar's visibility
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="dark:bg-boxdark-2 dark:text-bodydark">
            {/* Page Wrapper */}
            <div className="flex h-screen overflow-hidden">
                {/* Sidebar Component */}
                <Sidebar
                    sidebarOpen={sidebarOpen}
                    setSidebarOpen={setSidebarOpen}
                />

                {/* Main Content Area */}
                <div className="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
                    {/* Header Component */}
                    <Header
                        sidebarOpen={sidebarOpen}
                        setSidebarOpen={setSidebarOpen}
                    />

                    {/* Main Content */}
                    <main>
                        <div className="mx-auto max-w-screen-2xl p-4 md:p-6 2xl:p-10">
                            {children}
                        </div>
                    </main>
                </div>
            </div>
        </div>
    );
}
