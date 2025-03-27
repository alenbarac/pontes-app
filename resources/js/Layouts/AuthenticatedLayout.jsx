import React from "react";
import { usePage } from "@inertiajs/react";
import { SidebarProvider, useSidebar } from "@/context/SidebarContext"; // Adjust the import paths as needed
import Sidebar from "@/Layouts/AppSidebar";
import Header from "@/Layouts/AppHeader";
import Backdrop from "@/Layouts/Backdrop";

const LayoutContent = ({ children }) => {
    const { isExpanded, isHovered, isMobileOpen } = useSidebar();

    return (
        <div className="min-h-screen xl:flex dark:bg-boxdark-2 dark:text-bodydark">
            {/* Sidebar Section */}
            <div>
                <Sidebar />
                <Backdrop />
            </div>

            {/* Main Content Section */}
            <div
                className={`flex-1 transition-all duration-300 ease-in-out ${
                    isExpanded || isHovered ? "lg:ml-[290px]" : "lg:ml-[90px]"
                } ${isMobileOpen ? "ml-0" : ""}`}
            >
                <Header />
                <div className="p-4 mx-auto max-w-screen-2xl md:p-6 2xl:p-10">
                    {children}
                </div>
            </div>
        </div>
    );
};

const AuthenticatedLayout = ({ children }) => {
    // You can still use usePage to access authenticated user data if needed:
    // const user = usePage()?.props?.auth?.user;
    return (
        <SidebarProvider>
            <LayoutContent>{children}</LayoutContent>
        </SidebarProvider>
    );
};

export default AuthenticatedLayout;
