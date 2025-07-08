import React, { useCallback, useEffect, useRef, useState } from "react";
import { Link } from "@inertiajs/react";
import {
    HomeIcon,
    UsersIcon,
    ChevronDownIcon,
    SquaresPlusIcon,
    DocumentArrowUpIcon,
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
    {
        icon: <SquaresPlusIcon className="w-5 h-5" />,
        name: "Grupe polaznika",
        subItems: [
            { name: "Popis grupa", path: "/member-groups" },
            { name: "Nova grupa", path: "/member-groups/create" },
        ],
    },

    {
        icon: <DocumentArrowUpIcon className="w-5 h-5" />,
        name: "Uvoz podataka",
        subItems: [
            { name: "Uvoz članova", path: "/members/import" },
            { name: "Uvoz grupa", path: "/member-groups/create" },
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

    const renderMenuItems = (
        items: NavItem[],
        menuType: "main" | "support" | "others",
    ) => (
        <ul className="flex flex-col gap-4">
            {items.map((nav, index) => (
                <li key={nav.name}>
                    {nav.subItems ? (
                        <>
                            <button
                                onClick={() => handleSubmenuToggle(index)}
                                className={`menu-item group ${
                                    /* openSubmenu?.type === menuType && */
                                    openSubmenu?.index === index
                                        ? "menu-item-active"
                                        : "menu-item-inactive"
                                } cursor-pointer ${
                                    !isExpanded && !isHovered
                                        ? "lg:justify-center"
                                        : "lg:justify-start"
                                }`}
                            >
                                <span
                                    className={`menu-item-icon-size  ${
                                        openSubmenu?.index === index
                                            ? "menu-item-icon-active"
                                            : "menu-item-icon-inactive"
                                    }`}
                                >
                                    {nav.icon}
                                </span>
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
                                                    className={`menu-dropdown-item ${
                                                        isActive(subItem.path)
                                                            ? "menu-dropdown-item-active"
                                                            : "menu-dropdown-item-inactive"
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
                                className={`menu-item group ${
                                    isActive(nav.path)
                                        ? "menu-item-active"
                                        : "menu-item-inactive"
                                } ${!isExpanded && !isHovered ? "justify-center" : "justify-start"}`}
                            >
                                <span>{nav.icon}</span>
                                {(isExpanded || isHovered || isMobileOpen) && (
                                    <span className="menu-item-text">
                                        {nav.name}
                                    </span>
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
            className={`fixed mt-16 flex flex-col lg:mt-0 top-0 px-5 left-0 bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 h-screen transition-all duration-300 ease-in-out z-50 border-r border-gray-200 
        ${
            isExpanded || isMobileOpen
                ? "w-[290px]"
                : isHovered
                  ? "w-[290px]"
                  : "w-[90px]"
        }
        ${isMobileOpen ? "translate-x-0" : "-translate-x-full"}
        lg:translate-x-0`}
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
                                src="/images/logo/pontes-app-logo-main.svg"
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
                <nav className="mb-6">{renderMenuItems(navItems, "main")}</nav>
            </div>
        </aside>
    );
};

export default AppSidebar;
