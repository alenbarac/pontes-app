import { Cog8ToothIcon, IdentificationIcon, TrashIcon } from "@heroicons/react/24/outline";

export const groupColorMap = {
    "Juniori 1": "bg-primary/15 text-dark",
    "Juniori 2": "bg-secondary/15 text-dark",
    "Srednjoškolci 1": "bg-danger/15 text-dark",
    "Srednjoškolci 2": "bg-warning/15 text-dark",
    "Memorabilije 1": "bg-success/15 text-dark",
    "Memorabilije 2": "bg-meta-1/15 text-dark",
    "Memorabilije 3": "bg-meta-3/15 text-dark",
    "Mini 1": "bg-meta-5/15 text-dark",
    "Mini 2": "bg-meta-6/15 text-dark",
};


export const columns = [
    {
        header: "ID",
        accessorKey: "id",
    },
    {
        header: "Ime",
        accessorKey: "first_name",
    },
    {
        header: "Prezime",
        accessorKey: "last_name",
    },
    {
        header: "Godina rođenja",
        accessorKey: "birth_year",
    },
    {
        header: "Kontakt",
        accessorKey: "email",
        cell: (info) => {
            const email = info.getValue();
            return email ? (
                <a
                    href={`mailto:${email}`}
                    className="text-primary hover:underline"
                >
                    {email}
                </a>
            ) : (
                <span className="text-gray-500">Nema email</span>
            );
        },
    },
    {
        header: "Grupe",
        accessorKey: "groups",
        cell: (info) => {
            const groups = info.getValue() || [];
            console.log(groups);
            return (
                <div className="flex flex-wrap gap-1">
                    {groups.map((group) => (
                        <span
                            key={group.id}
                            className={`px-2 text-xs py-1 rounded ${groupColorMap[group.name]}`}
                        >
                            {group.name}
                        </span>
                    ))}
                </div>
            );
        },        
    },
    {
      header:"Radionice",
      accessorKey:"workshops",
      cell:(info)=>{
        const workshops = info.getValue() || [];
        return (
          <div>
            {workshops.map((workshop)=>(
              <div
                key={workshop.id}
                className={`px-2 py-1 rounded ${groupColorMap[workshop.name]}`}
              >
                {workshop.name}
              </div>
            ))}
          </div>
        );
      }
    },
    {
        header: "Akcije",
        accessorKey: "actions",
        cell: (info) => {
            return (
                <div className="flex space-x-1 justify-between">
                    <button className="hover:text-blue-700 mx-1">
                        <IdentificationIcon className="h-5 w-5" />
                    </button>
                    <button className="hover:text-yellow-700">
                        <Cog8ToothIcon className="h-5 w-5" />
                    </button>
                    <button className="hover:text-red-700">
                        <TrashIcon className="h-5 w-5" />
                    </button>
                </div>
            );
        },
    },
];