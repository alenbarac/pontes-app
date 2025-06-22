import { useModal } from "@/hooks/useModal";
import { format } from "date-fns";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";
import Label from "../form/Label";
import Input from "../form/input/InputField";
import MemberDetailEditForm from "./MemberDetailEditForm";
import Badge from "@/ui/badge/Badge";
import { PencilIcon } from "@heroicons/react/24/outline";

const MemberInfoCard = ({memberData}) => {
  const { isOpen, openModal, closeModal } = useModal();

  console.log("MemberInfoCard", memberData);
  

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
      </div>
  );
}
export default MemberInfoCard