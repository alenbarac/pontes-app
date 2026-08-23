import { Link } from "@inertiajs/react";
import { useState } from "react";
import {
  ArrowRightIcon,
  BanknotesIcon,
  ChartBarIcon,
  CheckCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  ScaleIcon,
} from "@heroicons/react/24/outline";

function formatCurrency(value) {
  return new Intl.NumberFormat("hr-HR", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value || 0));
}

function formatRate(value) {
  return `${Number(value || 0).toLocaleString("hr-HR", {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })}%`;
}

export default function CashFlowWidget({ revenue }) {
  const [period, setPeriod] = useState("current_month");

  const periods = revenue?.periods || {};
  const currentMonth = periods.current_month || {};
  const schoolYear = periods.school_year || {};
  const active = period === "school_year" ? schoolYear : currentMonth;

  const cards = [
    {
      title: "Očekivano",
      amount: formatCurrency(active.expected_amount),
      subtitle: "Svi izdani računi za period",
      icon: ScaleIcon,
      iconClass: "text-brand-500",
    },
    {
      title: "Naplaćeno",
      amount: formatCurrency(active.collected_amount),
      subtitle: "Već primljene uplate",
      icon: BanknotesIcon,
      iconClass: "text-success-500",
    },
    {
      title: "Stopa naplate",
      amount: formatRate(active.collection_rate),
      subtitle: "Naplaćeno u odnosu na očekivano",
      icon: ChartBarIcon,
      iconClass: "text-brand-500",
    },
    {
      title: "Kasni s uplatom",
      amount: formatCurrency(active.overdue),
      subtitle:
        active.late_member_count > 0
          ? `Prošao rok — ${active.late_member_count} članova`
          : "Prošao rok, još nije plaćeno",
      icon: ClockIcon,
      iconClass: "text-warning-500",
    },
  ];

  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white">
            Cash flow — {active.label}
          </h3>

          <div className="inline-flex w-fit items-center gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-900">
            <button
              type="button"
              onClick={() => setPeriod("current_month")}
              className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                period === "current_month"
                  ? "bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white"
                  : "text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
              }`}
            >
              {currentMonth.button_label || "Tekući mjesec"}
            </button>
            <button
              type="button"
              onClick={() => setPeriod("school_year")}
              className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                period === "school_year"
                  ? "bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white"
                  : "text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
              }`}
            >
              {schoolYear.button_label || "Školska godina"}
            </button>
          </div>
        </div>

        {active.is_healthy ? (
          <div className="flex items-center gap-2 text-sm text-success-600 dark:text-success-400">
            <CheckCircleIcon className="h-5 w-5 shrink-0" />
            <span>
              {period === "school_year"
                ? "Naplata školske godine uredna"
                : "Naplata mjeseca uredna"}
            </span>
          </div>
        ) : (
          <div className="flex items-center gap-2 text-sm text-warning-600 dark:text-warning-400">
            <ExclamationTriangleIcon className="h-5 w-5 shrink-0" />
            <span>{active.late_count || 0} članarina kasni</span>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => {
          const Icon = card.icon;

          return (
            <Link
              key={card.title}
              href="/invoices"
              className="rounded-xl border border-gray-200 p-4 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900/50"
            >
              <div className="mb-2 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Icon className={`h-5 w-5 ${card.iconClass}`} />
                  <p className="text-sm text-gray-500 dark:text-gray-400">{card.title}</p>
                </div>
                <ArrowRightIcon className="h-4 w-4 text-gray-400 dark:text-gray-500" />
              </div>

              <p className="text-xl font-semibold text-gray-900 dark:text-white">
                {card.amount}
              </p>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{card.subtitle}</p>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
