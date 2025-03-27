import { Link } from "@inertiajs/react";

interface BreadcrumbProps {
    pageName: string;
}
const Breadcrumb = ({ pageName }: BreadcrumbProps) => {
    return (
        <div className="mb-5">
            <nav>
                <ol className="flex flex-wrap items-center gap-1.5">
                    <li>
                        <Link
                            className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400"
                            href="/"
                        >
                            Početna
                        </Link>
                    </li>
                    <li className="flex items-center gap-1.5 text-sm text-gray-800 dark:text-white/90">
                        <span className="text-gray-500 dark:text-gray-400">
                            <svg
                                className="stroke-current"
                                width="16"
                                height="16"
                                viewBox="0 0 16 16"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M5.83333 12.6665L10 8.49984L5.83333 4.33317"
                                    stroke=""
                                    strokeWidth="1.2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </span>

                        <span> {pageName} </span>
                    </li>
                </ol>
            </nav>
        </div>
    );
};

export default Breadcrumb;
