import { Link } from "@inertiajs/react";
import {
  ArrowRightIcon,
  UsersIcon,
  UserPlusIcon,
  DocumentArrowUpIcon,
  DocumentTextIcon,
} from "@heroicons/react/24/outline";

const actions = [
  {
    title: "Popis članova",
    description: "Pregled svih članova",
    href: "/members",
    icon: UsersIcon,
  },
  {
    title: "Novi Upis",
    description: "Brzi unos novog člana",
    href: "/members/create",
    icon: UserPlusIcon,
  },
  {
    title: "Uvoz-mbanking",
    description: "Usklađivanje uplata",
    href: "/invoices/import-mbanking",
    icon: DocumentArrowUpIcon,
  },
  {
    title: "Ispričnice",
    description: "Dokumenti i predlošci",
    href: "/document-templates/ispricnice",
    icon: DocumentTextIcon,
  },
];

export default function DashboardQuickActions() {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="mb-4">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white">
          Brze radnje
        </h3>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Najčešće korišteni ekrani
        </p>
      </div>

      <div className="space-y-3">
        {actions.map((action) => {
          const Icon = action.icon;

          return (
            <Link
              key={action.title}
              href={action.href}
              className="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900/50"
            >
              <div className="flex items-center gap-3">
                <Icon className="h-5 w-5 text-brand-500" />
                <div>
                  <p className="text-sm font-medium text-gray-900 dark:text-white">
                    {action.title}
                  </p>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    {action.description}
                  </p>
                </div>
              </div>

              <ArrowRightIcon className="h-4 w-4 text-gray-400 dark:text-gray-500" />
            </Link>
          );
        })}
      </div>
    </div>
  );
}
