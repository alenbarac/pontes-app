import { AcademicCapIcon, SquaresPlusIcon, UsersIcon, PencilIcon, TrashIcon } from "@heroicons/react/24/outline";
import Button from "@/ui/button/Button";

export default function WorkshopCard({ workshop, onEdit, onDelete }) {
  return (
    <div className="group rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 hover:border-brand-500 dark:hover:border-brand-400 hover:shadow-md transition-all">
      {/* Workshop Header */}
      <div className="mb-3">
        <div className="flex items-start justify-between mb-1">
          <div className="flex items-center gap-2 flex-1">
            <AcademicCapIcon className="w-5 h-5 text-brand-500 flex-shrink-0" />
            <h4 className="text-base font-semibold text-gray-800 dark:text-white/90 line-clamp-1">
              {workshop.name}
            </h4>
          </div>
        </div>
        {workshop.type && (
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {workshop.type}
          </p>
        )}
      </div>

      {/* Stats */}
      <div className="space-y-2 mb-3 pb-3 border-b border-gray-200 dark:border-gray-800">
        <div className="flex items-center gap-2">
          <SquaresPlusIcon className="w-4 h-4 text-gray-400 dark:text-gray-500" />
          <span className="text-xs text-gray-500 dark:text-gray-400">
            {workshop.groups_count || 0} {(workshop.groups_count || 0) === 1 ? 'grupa' : (workshop.groups_count || 0) < 5 ? 'grupe' : 'grupa'}
          </span>
        </div>
        <div className="flex items-center gap-2">
          <UsersIcon className="w-4 h-4 text-gray-400 dark:text-gray-500" />
          <span className="text-xs text-gray-500 dark:text-gray-400">
            {workshop.members_count || 0} {(workshop.members_count || 0) === 1 ? 'član' : (workshop.members_count || 0) < 5 ? 'člana' : 'članova'}
          </span>
        </div>
      </div>

      {/* Workshop Description */}
      <div className="mb-3">
        {workshop.description ? (
          <p className="text-xs text-gray-600 dark:text-gray-400 line-clamp-3">
            {workshop.description}
          </p>
        ) : (
          <p className="text-xs text-gray-500 dark:text-gray-400 italic">
            Nema opisa
          </p>
        )}
      </div>

      {/* Action Buttons */}
      <div className="flex items-center gap-2 mt-3">
        <Button
          onClick={() => onEdit(workshop)}
          variant="outline"
          size="sm"
          className="flex-1"
        >
          <PencilIcon className="w-4 h-4 mr-1" />
          Uredi
        </Button>
        <Button
          onClick={() => onDelete(workshop)}
          variant="outline"
          size="sm"
          className="flex-1 text-red-600 hover:text-red-700 hover:border-red-300 dark:text-red-400 dark:hover:text-red-300"
        >
          <TrashIcon className="w-4 h-4 mr-1" />
          Obriši
        </Button>
      </div>
    </div>
  );
}
