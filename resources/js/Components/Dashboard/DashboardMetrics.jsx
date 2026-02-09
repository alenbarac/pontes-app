import { Link } from "@inertiajs/react";
import {
  UsersIcon,
  DocumentCurrencyEuroIcon,
  SquaresPlusIcon,
  AcademicCapIcon,
  ArrowRightIcon,
} from "@heroicons/react/24/outline";

export default function DashboardMetrics({ stats }) {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white">
            Pregled
          </h3>
        </div>
      </div>
      <div className="grid rounded-2xl border border-gray-200 bg-white sm:grid-cols-2 xl:grid-cols-5 dark:border-gray-800 dark:bg-gray-900">
        <Link
          href="/members"
          className="border-b border-gray-200 px-6 py-5 sm:border-r xl:border-b-0 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors cursor-pointer"
        >
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-3">
              <UsersIcon className="w-5 h-5 text-brand-500" />
              <span className="text-sm text-gray-500 dark:text-gray-400">
                Ukupno članova
              </span>
            </div>
            <ArrowRightIcon className="w-4 h-4 text-gray-400 dark:text-gray-500" />
          </div>
          <div className="mt-2 flex items-end gap-3">
            <h4 className="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
              {stats?.total_members || 0}
            </h4>
          </div>
        </Link>
        <div className="border-b border-gray-200 px-6 py-5 xl:border-r xl:border-b-0 dark:border-gray-800">
          <div className="flex items-center gap-3 mb-2">
            <DocumentCurrencyEuroIcon className="w-5 h-5 text-warning-500" />
            <span className="text-sm text-gray-500 dark:text-gray-400">
              Otvoreni računi
            </span>
          </div>
          <div className="mt-2 flex items-end gap-3">
            <h4 className="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
              {stats?.opened_invoices || 0}
            </h4>
          </div>
        </div>
        <Link
          href="/invoices"
          className="border-b border-gray-200 px-6 py-5 sm:border-r sm:border-b-0 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors cursor-pointer"
        >
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-3">
              <DocumentCurrencyEuroIcon className="w-5 h-5 text-brand-500" />
              <span className="text-sm text-gray-500 dark:text-gray-400">
                Ukupno računa
              </span>
            </div>
            <ArrowRightIcon className="w-4 h-4 text-gray-400 dark:text-gray-500" />
          </div>
          <div className="mt-2 flex items-end gap-3">
            <h4 className="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
              {stats?.total_invoices || 0}
            </h4>
          </div>
        </Link>
        <Link
          href="/member-groups"
          className="border-b border-gray-200 px-6 py-5 xl:border-r xl:border-b-0 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors cursor-pointer"
        >
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-3">
              <SquaresPlusIcon className="w-5 h-5 text-brand-500" />
              <span className="text-sm text-gray-500 dark:text-gray-400">
                Ukupno grupa
              </span>
            </div>
            <ArrowRightIcon className="w-4 h-4 text-gray-400 dark:text-gray-500" />
          </div>
          <div className="mt-2 flex items-end gap-3">
            <h4 className="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
              {stats?.total_groups || 0}
            </h4>
          </div>
        </Link>
        <div className="px-6 py-5">
          <div className="flex items-center gap-3 mb-2">
            <AcademicCapIcon className="w-5 h-5 text-brand-500" />
            <span className="text-sm text-gray-500 dark:text-gray-400">
              Ukupno radionica
            </span>
          </div>
          <div className="mt-2 flex items-end gap-3">
            <h4 className="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
              {stats?.total_workshops || 0}
            </h4>
          </div>
        </div>
      </div>
    </div>
  );
}
