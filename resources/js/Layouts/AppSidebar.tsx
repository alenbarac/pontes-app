import React, { useCallback, useEffect, useRef, useState } from "react";
import { Link } from "@inertiajs/react";
import {
    HomeIcon,
    UsersIcon,
    ChevronDownIcon,
} from "@heroicons/react/24/outline";
import { useSidebar } from "@/context/SidebarContext";

type NavItem = {
    name: string;
    icon: React.ReactNode;
    path?: string;
    subItems?: { name: string; path: string }[];
};

// Define your navigation items – adjust as needed.
const navItems: NavItem[] = [
    {
        icon: <HomeIcon className="w-5 h-5" />,
        name: "Početna",
        path: "/dashboard",
    },
    {
        icon: <UsersIcon className="w-5 h-5" />,
        name: "Članovi",
        subItems: [
            { name: "Popis članova", path: "/members" },
            { name: "Novi upis", path: "/members/create" },
        ],
    },
];

const AppSidebar: React.FC = () => {
    const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();

    // A simple active check using window.location.pathname.
    const isActive = useCallback(
        (path: string) => window.location.pathname === path,
        [],
    );

    // State to track which submenu is open.
    const [openSubmenu, setOpenSubmenu] = useState<{ index: number } | null>(
        null,
    );
    const [subMenuHeight, setSubMenuHeight] = useState<Record<string, number>>(
        {},
    );
    const subMenuRefs = useRef<Record<string, HTMLDivElement | null>>({});

    // On mount, open submenu if any subitem matches current URL.
    useEffect(() => {
        navItems.forEach((item, index) => {
            if (item.subItems) {
                item.subItems.forEach((subItem) => {
                    if (isActive(subItem.path)) {
                        setOpenSubmenu({ index });
                    }
                });
            }
        });
    }, [isActive]);

    // Calculate submenu height for smooth animations.
    useEffect(() => {
        if (openSubmenu !== null) {
            const key = `${openSubmenu.index}`;
            if (subMenuRefs.current[key]) {
                setSubMenuHeight((prev) => ({
                    ...prev,
                    [key]: subMenuRefs.current[key]?.scrollHeight || 0,
                }));
            }
        }
    }, [openSubmenu]);

    const handleSubmenuToggle = (index: number) => {
        setOpenSubmenu((prev) =>
            prev && prev.index === index ? null : { index },
        );
    };

    const renderMenuItems = (items: NavItem[]) => (
        <ul className="flex flex-col gap-4">
            {items.map((nav, index) => (
                <li key={nav.name}>
                    {nav.subItems ? (
                        <>
                            <button
                                onClick={() => handleSubmenuToggle(index)}
                                className={`flex items-center gap-2 px-4 py-2 font-medium transition-colors w-full ${
                                    openSubmenu?.index === index
                                        ? "bg-gray-700 text-white"
                                        : "text-gray-300 hover:bg-gray-600"
                                } ${!isExpanded && !isHovered ? "justify-center" : "justify-start"}`}
                            >
                                <span>{nav.icon}</span>
                                {(isExpanded || isHovered || isMobileOpen) && (
                                    <span>{nav.name}</span>
                                )}
                                {(isExpanded || isHovered || isMobileOpen) && (
                                    <ChevronDownIcon
                                        className={`ml-auto w-5 h-5 transition-transform duration-200 ${
                                            openSubmenu?.index === index
                                                ? "rotate-180"
                                                : ""
                                        }`}
                                    />
                                )}
                            </button>
                            {(isExpanded || isHovered || isMobileOpen) && (
                                <div
                                    ref={(el) => {
                                        subMenuRefs.current[`${index}`] = el;
                                    }}
                                    className="overflow-hidden transition-all duration-300"
                                    style={{
                                        height:
                                            openSubmenu?.index === index
                                                ? `${subMenuHeight[`${index}`] || 0}px`
                                                : "0px",
                                    }}
                                >
                                    <ul className="mt-2 space-y-1 ml-9">
                                        {nav.subItems.map((subItem) => (
                                            <li key={subItem.name}>
                                                <Link
                                                    href={subItem.path}
                                                    className={`block px-4 py-1 text-sm transition-colors ${
                                                        isActive(subItem.path)
                                                            ? "bg-gray-700 text-white"
                                                            : "text-gray-300 hover:bg-gray-600"
                                                    }`}
                                                >
                                                    {subItem.name}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </>
                    ) : (
                        nav.path && (
                            <Link
                                href={nav.path}
                                className={`flex items-center gap-2 px-4 py-2 font-medium transition-colors w-full ${
                                    isActive(nav.path)
                                        ? "bg-gray-700 text-white"
                                        : "text-gray-300 hover:bg-gray-600"
                                } ${!isExpanded && !isHovered ? "justify-center" : "justify-start"}`}
                            >
                                <span>{nav.icon}</span>
                                {(isExpanded || isHovered || isMobileOpen) && (
                                    <span>{nav.name}</span>
                                )}
                            </Link>
                        )
                    )}
                </li>
            ))}
        </ul>
    );

    return (
        <aside
            className={`fixed top-0 left-0 z-50 h-screen border-r border-gray-200 bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 transition-all duration-300 ease-in-out 
        ${isExpanded || isMobileOpen ? "w-[290px]" : isHovered ? "w-[290px]" : "w-[90px]"}
        ${isMobileOpen ? "translate-x-0" : "-translate-x-full"} lg:translate-x-0`}
            onMouseEnter={() => !isExpanded && setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            {/* Sidebar Header */}
            <div
                className={`py-8 flex ${!isExpanded && !isHovered ? "justify-center" : "justify-start"}`}
            >
                <Link href="/">
                    {isExpanded || isHovered || isMobileOpen ? (
                        <>
                            <img
                                className="dark:hidden"
                                src="/images/logo/logo.svg"
                                alt="Logo"
                                width={150}
                                height={40}
                            />
                            <img
                                className="hidden dark:block"
                                src="/images/logo/logo-dark.svg"
                                alt="Logo"
                                width={150}
                                height={40}
                            />
                        </>
                    ) : (
                        <img
                            src="/images/logo/logo-icon.svg"
                            alt="Logo"
                            width={32}
                            height={32}
                        />
                    )}
                </Link>
            </div>

            {/* Navigation Menu */}
            <div className="flex flex-col overflow-y-auto transition-all duration-300 ease-linear no-scrollbar">
                <nav className="mb-6">{renderMenuItems(navItems)}</nav>
               
            </div>
        </aside>
    );
};

export default AppSidebar;
