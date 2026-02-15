import { Link } from "@inertiajs/react";
import { SquaresPlusIcon, UsersIcon } from "@heroicons/react/24/outline";

export default function GroupCards({ groups = [] }) {
  if (groups.length === 0) {
    return (
      <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div className="px-6 py-4">
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            Grupe
          </h3>
        </div>
        <div className="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
          Nema grupa za prikaz
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="px-6 py-4 flex justify-between items-center">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
          Grupe
        </h3>
        <Link
          href="/member-groups"
          className="text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
        >
          Vidi sve →
        </Link>
      </div>
      <div className="px-6 pb-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          {groups.map((group) => {
            return (
              <Link
                key={group.id}
                href={route("member-groups.show", group.id)}
                className="group rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 hover:border-brand-500 dark:hover:border-brand-400 hover:shadow-md transition-all cursor-pointer"
              >
                {/* Group Header */}
                <div className="mb-3">
                  <div className="flex items-start justify-between mb-1">
                    <div className="flex items-center gap-2">
                      <SquaresPlusIcon className="w-4 h-4 text-brand-500 flex-shrink-0" />
                      <h4 className="text-sm font-semibold text-gray-800 dark:text-white/90 line-clamp-1">
                        {group.name}
                      </h4>
                    </div>
                  </div>
                  {group.workshop && (
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                      {group.workshop.name}
                    </p>
                  )}
                </div>

                {/* Member Count */}
                <div className="flex items-center gap-2 mb-3 pb-3 border-b border-gray-200 dark:border-gray-800">
                  <UsersIcon className="w-4 h-4 text-gray-400 dark:text-gray-500" />
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    {group.members_count || 0} {(group.members_count || 0) === 1 ? 'član' : (group.members_count || 0) < 5 ? 'člana' : 'članova'}
                  </span>
                </div>

                {/* Group Description */}
                <div className="mt-3">
                  {group.description ? (
                    <p className="text-xs text-gray-600 dark:text-gray-400 line-clamp-3">
                      {group.description}
                    </p>
                  ) : (
                    <p className="text-xs text-gray-500 dark:text-gray-400 italic">
                      Nema opisa
                    </p>
                  )}
                </div>
              </Link>
            );
          })}
        </div>
      </div>
    </div>
  );
}
