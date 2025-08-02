
import { cn } from "@/lib/utils";

interface StatusBadgeProps {
  status: "available" | "occupied" | "cleaning" | "verified" | "pending" | "rejected";
  className?: string;
}

const statusClasses = {
  available: "bg-green-100 text-green-800",
  occupied: "bg-red-100 text-red-800",
  cleaning: "bg-yellow-100 text-yellow-800",
  verified: "bg-green-100 text-green-800",
  pending: "bg-yellow-100 text-yellow-800",
  rejected: "bg-red-100 text-red-800",
};

const statusLabels = {
  available: "Available",
  occupied: "Occupied",
  cleaning: "Cleaning",
  verified: "Verified",
  pending: "Pending",
  rejected: "Rejected",
};

export const StatusBadge = ({ status, className }: StatusBadgeProps) => {
  return (
    <span
      className={cn(
        "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium",
        statusClasses[status],
        className
      )}
    >
      {statusLabels[status]}
    </span>
  );
};
