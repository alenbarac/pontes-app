import React from "react";
import { useForm } from "@inertiajs/react";
import Button from "@/ui/button/Button";
import Label from "../form/Label";
import Input from "../form/input/InputField";
import toast from "react-hot-toast";

export default function MemberGroupEditForm({ group, onClose }) {
  const { data, setData, put, processing, errors } = useForm({
    name: group.name || "",
    description: group.description || "",
  });


  const handleSubmit = (e) => {
    e.preventDefault();

    put(route("member-groups.update", group.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Grupa uspješno ažurirana.");
            onClose();
        },
        onError: () => {
            toast.error("Došlo je do pogreške.");
        },
    });
  };

  return (
      <form onSubmit={handleSubmit} className="space-y-6">
          <div>
              <Label htmlFor="name">Naziv grupe</Label>
              <Input
                  type="text"
                  id="name"
                  placeholder="Unesite naziv grupe"
                  value={data.name}
                  onChange={(e) => setData("name", e.target.value)}
              />
              {errors.name && (
                  <p className="text-red-500 text-sm">{errors.name}</p>
              )}
          </div>

          <div>
              <Label htmlFor="description">Opis</Label>
              <Input
                  type="text"
                  id="description"
                  placeholder="Unesite opis grupe"
                  value={data.description}
                  onChange={(e) => setData("description", e.target.value)}
              />
              {errors.description && (
                  <p className="text-red-500 text-sm">{errors.description}</p>
              )}
          </div>

          <div className="flex justify-end">
              <Button type="submit" variant="primary" disabled={processing}>
                  Spremi promjene
              </Button>
          </div>
      </form>
  );
}
