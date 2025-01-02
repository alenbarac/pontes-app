import React, { useEffect, useRef, useState } from "react";
import { Link } from "@inertiajs/react";
import SidebarLinkGroup from "./SidebarLinkGroup"; // Ensure this component exists
import {
    HomeIcon,
    UsersIcon,
    ChevronDownIcon,
} from "@heroicons/react/24/outline";

interface SidebarProps {
    sidebarOpen: boolean;
    setSidebarOpen: (arg: boolean) => void;
}

const Sidebar = ({ sidebarOpen, setSidebarOpen }: SidebarProps) => {
    const trigger = useRef<any>(null);
    const sidebar = useRef<any>(null);

    const storedSidebarExpanded = localStorage.getItem("sidebar-expanded");
    const [sidebarExpanded, setSidebarExpanded] = useState(
        storedSidebarExpanded === null
            ? false
            : storedSidebarExpanded === "true",
    );

    // Close on click outside
    useEffect(() => {
        const clickHandler = ({ target }: MouseEvent) => {
            if (!sidebar.current || !trigger.current) return;
            if (
                !sidebarOpen ||
                sidebar.current.contains(target) ||
                trigger.current.contains(target)
            )
                return;
            setSidebarOpen(false);
        };
        document.addEventListener("click", clickHandler);
        return () => document.removeEventListener("click", clickHandler);
    });

    useEffect(() => {
        localStorage.setItem("sidebar-expanded", sidebarExpanded.toString());
        if (sidebarExpanded) {
            document.querySelector("body")?.classList.add("sidebar-expanded");
        } else {
            document
                .querySelector("body")
                ?.classList.remove("sidebar-expanded");
        }
    }, [sidebarExpanded]);

    return (
        <aside
            ref={sidebar}
            className={`absolute left-0 top-0 z-9999 flex h-screen w-80 flex-col overflow-y-hidden bg-black duration-300 ease-linear dark:bg-boxdark lg:static lg:translate-x-0 ${
                sidebarOpen ? "translate-x-0" : "-translate-x-full"
            }`}
        >
            {/* Sidebar Header */}
            <div className="flex items-center justify-between gap-2 px-6 py-5.5 lg:py-6.5">
                <Link href="/">
                    <img src="/images/logo/logo.svg" alt="Logo" />
                </Link>
                <button
                    ref={trigger}
                    onClick={() => setSidebarOpen(!sidebarOpen)}
                    aria-controls="sidebar"
                    aria-expanded={sidebarOpen}
                    className="block lg:hidden"
                >
                    <HomeIcon className="w-6 h-6 text-white" />
                </button>
            </div>

            {/* Sidebar Menu */}
            <div className="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear">
                <nav className="mt-5 py-4 px-4 lg:mt-9 lg:px-6">
                    <h3 className="mb-4 ml-4 text-sm font-semibold text-bodydark2">
                        MENU
                    </h3>
                    <ul className="mb-6 flex flex-col gap-1.5">
                        {/* Dashboard Link */}
                        <li>
                            <Link
                                href="/dashboard"
                                className={`group relative flex items-center gap-2.5 rounded-sm px-4 py-2 font-medium text-bodydark1 hover:bg-graydark dark:hover:bg-meta-4 ${
                                    route().current("dashboard") &&
                                    "bg-graydark dark:bg-meta-4"
                                }`}
                            >
                                <HomeIcon className="w-5 h-5 text-white" />
                                Početna
                            </Link>
                        </li>

                        {/* Members Dropdown */}
                        <SidebarLinkGroup
                            activeCondition={
                                route().current("members.index") ||
                                route().current("members.create")
                            }
                        >
                            {(handleClick, open) => (
                                <>
                                    <Link
                                        href="#"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            sidebarExpanded
                                                ? handleClick()
                                                : setSidebarExpanded(true);
                                        }}
                                        className={`group relative flex items-center gap-2.5 rounded-sm px-4 py-2 font-medium text-bodydark1 hover:bg-graydark dark:hover:bg-meta-4 ${
                                            route().current("members.index") &&
                                            "bg-graydark dark:bg-meta-4"
                                        }`}
                                    >
                                        <UsersIcon className="w-5 h-5 text-white" />
                                        Članovi
                                        <ChevronDownIcon
                                            className={`absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-white ${
                                                open && "rotate-180"
                                            }`}
                                        />
                                    </Link>
                                    <div
                                        className={`translate transform overflow-hidden ${
                                            !open && "hidden"
                                        }`}
                                    >
                                        <ul className="mt-4 mb-5.5 flex flex-col gap-2.5 pl-6">
                                            <li>
                                                <Link
                                                    href="/members"
                                                    className="group relative flex items-center gap-2.5 rounded-md px-4 font-medium hover:text-white"
                                                >
                                                    Popis članova
                                                </Link>
                                            </li>
                                            <li>
                                                <Link
                                                    href="/members/create"
                                                    className={`group relative flex items-center gap-2.5 rounded-md px-4 font-medium hover:text-white ${
                                                        route().current(
                                                            "members.create",
                                                        ) && "text-white"
                                                    }`}
                                                >
                                                    Novi upis
                                                </Link>
                                            </li>
                                        </ul>
                                    </div>
                                </>
                            )}
                        </SidebarLinkGroup>
                    </ul>
                </nav>
            </div>
        </aside>
    );
};

export default Sidebar;
