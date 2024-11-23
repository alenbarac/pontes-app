import { ReactNode, useState } from "react";

interface SidebarLinkGroupProps {
    children: (handleClick: () => void, open: boolean) => ReactNode;
    activeCondition: boolean;
    className?: string; // Optional className prop
}

const SidebarLinkGroup = ({
    children,
    activeCondition,
    className = "",
}: SidebarLinkGroupProps) => {
    const [open, setOpen] = useState<boolean>(activeCondition);

    const handleClick = () => {
        setOpen(!open);
    };

    return <li className={className}>{children(handleClick, open)}</li>;
};

export default SidebarLinkGroup;
