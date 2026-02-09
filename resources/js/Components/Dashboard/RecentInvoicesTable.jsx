import { Link } from "@inertiajs/react";
import { EyeIcon } from "@heroicons/react/24/outline";
import Badge from "../ui/badge/Badge";

export default function RecentInvoicesTable({ invoices = [] }) {
  const getStatusColor = (status) => {
    if (status === "Plaćeno" || status === "Paid") {
      return "success";
    } else if (status === "Otvoreno" || status === "Pending") {
      return "warning";
    } else if (status === "Zakasnilo" || status === "Overdue") {
      return "error";
    }
    return "light";
  };

  if (invoices.length === 0) {
    return (
      <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div className="px-6 py-4">
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            Nedavni računi
          </h3>
        </div>
        <div className="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
          Nema računa za prikaz
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="px-6 py-4 flex justify-between items-center">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
          Nedavni računi
        </h3>
        <Link
          href="/invoices"
          className="text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
        >
          Vidi sve →
        </Link>
      </div>
      <div className="custom-scrollbar overflow-x-auto">
        <table className="min-w-full">
          <thead>
            <tr className="bg-gray-50 dark:bg-gray-900">
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Referentni broj
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Član
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Radionica
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Iznos
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Datum dospijeća
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Status
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Akcije
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
            {invoices.map((invoice) => (
              <tr key={invoice.id} className="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {invoice.reference_code}
                </td>
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {invoice.member_name}
                </td>
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {invoice.workshop_name}
                </td>
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {invoice.amount_due} €
                </td>
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {invoice.due_date || "-"}
                </td>
                <td className="px-6 py-4 text-left">
                  <Badge size="sm" color={getStatusColor(invoice.payment_status)}>
                    {invoice.payment_status}
                  </Badge>
                </td>
                <td className="px-6 py-4 text-left">
                  <Link
                    href={route("invoices.show", invoice.id)}
                    className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-brand-500 hover:bg-gray-100 dark:hover:bg-gray-800 dark:text-gray-400 dark:hover:text-brand-400 transition-colors"
                    title="Pogledaj detalje"
                  >
                    <EyeIcon className="w-5 h-5" />
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
