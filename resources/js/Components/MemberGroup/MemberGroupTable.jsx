import React from "react";
import {
  Table,
  TableHeader,
  TableRow,
  TableCell,
  TableBody,
} from "@/Components/ui/table"; // Adjust if your Table components come from another library


export default function MemberGroupTable({ groups }) {
  return (
    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
      <div className="max-w-full overflow-x-auto">
        <Table>
          <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
            <TableRow>
              <TableCell
                isHeader
                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
              >
                Naziv Grupe
              </TableCell>
              <TableCell
                isHeader
                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
              >
                Opis
              </TableCell>
             {/*  <TableCell
                isHeader
                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
              >
                Broj članova
              </TableCell> */}
              <TableCell
                isHeader
                className="px-5 py-3 font-medium text-gray-500 text-start text-theme-xs dark:text-gray-400"
              >
                Akcije
              </TableCell>
            </TableRow>
          </TableHeader>

          <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
            {groups.map((group) => (
              <TableRow key={group.id}>
                <TableCell className="px-5 py-4 text-start text-theme-sm dark:text-white/90">
                  {group.name}
                </TableCell>
                <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                  {group.description}
                </TableCell>
               {/*  <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                  {group.members_count}
                </TableCell> */}
                <TableCell className="px-5 py-4 text-start">
                  <div className="flex gap-2">
                    <a
                      href={`/member-groups/${group.id}/edit`}
                      className="text-brand-600 hover:underline text-sm"
                    >
                      Uredi
                    </a>
                    <a
                      href={`/member-groups/${group.id}`}
                      className="text-gray-600 hover:underline text-sm"
                    >
                      Pregled
                    </a>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
