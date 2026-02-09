import { Link } from "@inertiajs/react";
import { EyeIcon } from "@heroicons/react/24/outline";

export default function RecentMembersTable({ members = [] }) {
  if (members.length === 0) {
    return (
      <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div className="px-6 py-4">
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            Nedavni članovi
          </h3>
        </div>
        <div className="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
          Nema članova za prikaz
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="px-6 py-4 flex justify-between items-center">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
          Nedavni članovi
        </h3>
        <div className="flex items-center gap-3">
          <Link
            href="/members/create"
            className="inline-flex items-center justify-center gap-2 rounded-lg transition px-4 py-2 text-sm bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300"
          >
            Novi upis
          </Link>
          <Link
            href="/members"
            className="text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
          >
            Vidi sve →
          </Link>
        </div>
      </div>
      <div className="custom-scrollbar overflow-x-auto">
        <table className="min-w-full">
          <thead>
            <tr className="bg-gray-50 dark:bg-gray-900">
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Ime
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Prezime
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Email
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Datum dodavanja
              </th>
              <th className="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                Akcije
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
            {members.map((member) => (
              <tr key={member.id} className="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {member.first_name}
                </td>
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {member.last_name}
                </td>
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {member.email ? (
                    <a
                      href={`mailto:${member.email}`}
                      className="text-primary hover:underline"
                    >
                      {member.email}
                    </a>
                  ) : (
                    <span className="text-gray-500">Nema email</span>
                  )}
                </td>
                <td className="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                  {member.created_at || "-"}
                </td>
                <td className="px-6 py-4 text-left">
                  <Link
                    href={route("members.show", member.id)}
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
