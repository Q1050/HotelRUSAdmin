
import { cn } from "@/lib/utils";

interface StatusBadgeProps {
  status: "available" | "occupied" | "cleaning" | "pending_housekeeping" | "awaiting_inspection" | "maintenance_required" | "verified" | "pending" | "rejected";
  className?: string;
}

const statusClasses = {
  available: "bg-green-100 text-green-800",
  occupied: "bg-red-100 text-red-800",
  cleaning: "bg-yellow-100 text-yellow-800",
  pending_housekeeping: "bg-amber-100 text-amber-800",
  awaiting_inspection: "bg-blue-100 text-blue-800",
  maintenance_required: "bg-red-100 text-red-800",
  verified: "bg-green-100 text-green-800",
  pending: "bg-yellow-100 text-yellow-800",
  rejected: "bg-red-100 text-red-800",
};

const statusLabels = {
  available: "Available",
  occupied: "Occupied",
  cleaning: "Cleaning",
  pending_housekeeping: "Pending housekeeping",
  awaiting_inspection: "Awaiting inspection",
  maintenance_required: "Maintenance required",
  verified: "Verified",
  pending: "Pending",
  rejected: "Rejected",
};

export const StatusBadge = ({ status, className }: StatusBadgeProps) => {
  return (
    <span
      className={cn(
        "inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] leading-4 font-medium",
        statusClasses[status],
        className
      )}
    >
      {statusLabels[status]}
    </span>
  );
};
