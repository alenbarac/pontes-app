import { useModal } from "@/hooks/useModal";
import { format } from "date-fns";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";
import Label from "../form/Label";
import Input from "../form/input/InputField";
import MemberDetailEditForm from "./MemberDetailEditForm";
import Badge from "@/ui/badge/Badge";
import { PencilIcon, TrashIcon } from "@heroicons/react/24/outline";
import { router } from "@inertiajs/react";
import toast from "react-hot-toast";

const MemberInfoCard = ({memberData}) => {
  const { isOpen, openModal, closeModal } = useModal();
  const deleteModal = useModal();

  function handleDeleteMember() {
      deleteModal.openModal();
  }

  function handleDeleteConfirm() {
      router.delete(route("members.destroy", { member: memberData.id }), {
          onSuccess: () => {
              deleteModal.closeModal();
              toast.success("Član je uspješno obrisan.");
              
          },
          onError: () => {
              toast.error("Došlo je do pogreške pri brisanju člana.");
          },
      });
  }
  

  return (
      <div className="p-5 border border-gray-200 rounded-2xl dark:border-gray-800 lg:p-6">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
              <div>
                  <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90 lg:mb-6">
                      Osnovne informacije
                  </h4>

                  <div className="grid grid-cols-1 gap-4 lg:grid-cols-4 lg:gap-7 2xl:gap-x-32">
                      <div>
                          <p className="mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400">
                              Ime
                          </p>
                          <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                              {memberData?.first_name}
                          </p>
                      </div>

                      <div>
                          <p className="mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400">
                              Prezime
                          </p>
                          <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                              {memberData?.last_name}
                          </p>
                      </div>

                      <div>
                          <p className="mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400">
                              Datum rođenja
                          </p>
                          <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                              {format(
                                  new Date(memberData?.date_of_birth),
                                  "dd.MM.yyyy.",
                              )}
                          </p>
                      </div>

                      <div>
                          <p className="mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400">
                              Email addresa
                          </p>
                          <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                              {memberData?.email}
                          </p>
                      </div>

                      <div>
                          <p className="mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400">
                              Telefon
                          </p>
                          <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                              {memberData?.phone_number}
                          </p>
                      </div>

                      <div>
                          <p className="mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400">
                              Kontakt roditelja
                          </p>
                          <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                              {memberData?.parent_contact}
                          </p>
                      </div>
                      <div>
                          <p className="mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400">
                              Email roditelja
                          </p>
                          <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                              {memberData?.parent_email}
                          </p>
                      </div>
                      <div>
                          <p className="mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400">
                              Status člana
                          </p>
                          <Badge
                              variant="light"
                              color={
                                  memberData?.is_active ? "success" : "error"
                              }
                          >
                              {memberData?.is_active ? "Aktivan" : "Neaktivan"}
                          </Badge>
                      </div>
                  </div>
              </div>

              <Button
                  onClick={openModal}
                  variant="primary"
                  size="xs"
                  startIcon={<PencilIcon className="h-4 w-4" />}
                  className="mb-3"
              >
                  Uredi
              </Button>
          </div>
          <div className="flex justify-end mt-4">
              <Button
                  variant="outline"
                  startIcon={<TrashIcon className="h-5 w-5" />}
                  onClick={handleDeleteMember}
                  size="xs"
              >
                  Obriši člana
              </Button>
          </div>

          <Modal
              isOpen={isOpen}
              onClose={closeModal}
              className="max-w-[700px] m-4"
          >
              <div className="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11 mb-5">
                  <div className="px-2 pr-14">
                      <h4 className="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">
                          Uredi upis
                      </h4>
                  </div>
                  <MemberDetailEditForm
                      member={memberData}
                      closeModal={closeModal}
                  />
              </div>
          </Modal>

          <Modal
              isOpen={deleteModal.isOpen}
              onClose={deleteModal.closeModal}
              className="max-w-[600px] p-5 lg:p-10"
          >
              <div className="text-center">
                  <div className="relative flex items-center justify-center z-1 mb-7">
                      <svg
                          className="fill-error-50 dark:fill-error-500/15"
                          width="90"
                          height="90"
                          viewBox="0 0 90 90"
                          fill="none"
                          xmlns="http://www.w3.org/2000/svg"
                      >
                          <path
                              d="M34.364 6.85053C38.6205 -2.28351 51.3795 -2.28351 55.636 6.85053C58.0129 11.951 63.5594 14.6722 68.9556 13.3853C78.6192 11.0807 86.5743 21.2433 82.2185 30.3287C79.7862 35.402 81.1561 41.5165 85.5082 45.0122C93.3019 51.2725 90.4628 63.9451 80.7747 66.1403C75.3648 67.3661 71.5265 72.2695 71.5572 77.9156C71.6123 88.0265 60.1169 93.6664 52.3918 87.3184C48.0781 83.7737 41.9219 83.7737 37.6082 87.3184C29.8831 93.6664 18.3877 88.0266 18.4428 77.9156C18.4735 72.2695 14.6352 67.3661 9.22531 66.1403C-0.462787 63.9451 -3.30193 51.2725 4.49185 45.0122C8.84391 41.5165 10.2138 35.402 7.78151 30.3287C3.42572 21.2433 11.3808 11.0807 21.0444 13.3853C26.4406 14.6722 31.9871 11.951 34.364 6.85053Z"
                              fill=""
                              fillOpacity=""
                          />
                      </svg>

                      <span className="absolute -translate-x-1/2 -translate-y-1/2 left-1/2 top-1/2">
                          <svg
                              className="fill-error-600 dark:fill-error-500"
                              width="38"
                              height="38"
                              viewBox="0 0 38 38"
                              fill="none"
                              xmlns="http://www.w3.org/2000/svg"
                          >
                              <path
                                  fillRule="evenodd"
                                  clipRule="evenodd"
                                  d="M9.62684 11.7496C9.04105 11.1638 9.04105 10.2141 9.62684 9.6283C10.2126 9.04252 11.1624 9.04252 11.7482 9.6283L18.9985 16.8786L26.2485 9.62851C26.8343 9.04273 27.7841 9.04273 28.3699 9.62851C28.9556 10.2143 28.9556 11.164 28.3699 11.7498L21.1198 18.9999L28.3699 26.25C28.9556 26.8358 28.9556 27.7855 28.3699 28.3713C27.7841 28.9571 26.8343 28.9571 26.2485 28.3713L18.9985 21.1212L11.7482 28.3715C11.1624 28.9573 10.2126 28.9573 9.62684 28.3715C9.04105 27.7857 9.04105 26.836 9.62684 26.2502L16.8771 18.9999L9.62684 11.7496Z"
                                  fill=""
                              />
                          </svg>
                      </span>
                  </div>

                  <h4 className="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90 sm:text-title-sm">
                      Brisanje člana
                  </h4>
                  <p className="text-sm leading-6 text-gray-500 dark:text-gray-400">
                      Jeste li sigurni da želite izbrisati ovog člana? Ova
                      radnja je nepovratna i svi podaci vezani uz člana biti će
                      trajno izbrisani.
                  </p>

                  <div className="flex items-center justify-center w-full gap-3 mt-7">
                      <Button
                          onClick={handleDeleteConfirm}
                          variant="primary"
                          size="sm"
                      >
                          Da, potvrđujem
                      </Button>
                  </div>
              </div>
          </Modal>
      </div>
  );
}
export default MemberInfoCard