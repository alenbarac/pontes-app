import { Link } from "@inertiajs/react";
import {
  ArrowRightIcon,
  BanknotesIcon,
  ClockIcon,
  DocumentTextIcon,
  CheckBadgeIcon,
} from "@heroicons/react/24/outline";

function formatCurrency(value) {
  return new Intl.NumberFormat("hr-HR", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value || 0));
}

export default function RevenueInsights({ revenue }) {
  const data = revenue || {};

  const cards = [
    {
      title: "Prihod tekućeg mjeseca",
      amount: formatCurrency(data.current_month_revenue),
      subtitle: "Uplate evidentirane ovaj mjesec",
      count: null,
      icon: BanknotesIcon,
      iconClass: "text-success-500",
      href: "/invoices",
    },
    {
      title: "Čekanje naplate",
      amount: formatCurrency(data.due_invoices_amount),
      subtitle: "Otvoreni dospjeli računi",
      count: `${data.due_invoices_count || 0} računa`,
      icon: ClockIcon,
      iconClass: "text-warning-500",
      href: "/invoices",
    },
    {
      title: "Generirani računi (mjesec)",
      amount: formatCurrency(data.current_month_generated_amount),
      subtitle: "Računi sa dospijećem u tekućem mjesecu",
      count: `${data.current_month_generated_count || 0} računa`,
      icon: DocumentTextIcon,
      iconClass: "text-brand-500",
      href: "/invoices/generate",
    },
    {
      title: "Plaćeni računi (mjesec)",
      amount: formatCurrency(data.current_month_paid_amount),
      subtitle: "Računi tekućeg mjeseca sa statusom Plaćeno",
      count: `${data.current_month_paid_count || 0} računa`,
      icon: CheckBadgeIcon,
      iconClass: "text-success-500",
      href: "/invoices",
    },
  ];

  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="mb-6">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white">
          Prihodi i računi
        </h3>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => {
          const Icon = card.icon;

          return (
            <Link
              key={card.title}
              href={card.href}
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
              {card.count ? (
                <p className="mt-2 text-xs font-medium text-gray-700 dark:text-gray-300">
                  {card.count}
                </p>
              ) : null}
            </Link>
          );
        })}
      </div>
    </div>
  );
}
