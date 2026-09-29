import { Link } from "@inertiajs/react";
import {
  UsersIcon,
  UserPlusIcon,
  DocumentPlusIcon,
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
    title: "Generiranje računa",
    description: "Generiranje mjesečnih računa",
    href: "/invoices/generate",
    icon: DocumentPlusIcon,
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
    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
      <p className="shrink-0 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Brze radnje
      </p>
      <div className="flex flex-wrap gap-2">
        {actions.map((action) => {
          const Icon = action.icon;

          return (
            <Link
              key={action.title}
              href={action.href}
              title={action.description}
              className="inline-flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm font-medium text-brand-600 ring-1 ring-inset ring-brand-100 transition-colors hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-500/20 dark:hover:bg-brand-500/20"
            >
              <Icon className="h-4 w-4 shrink-0" />
              {action.title}
            </Link>
          );
        })}
      </div>
    </div>
  );
}
